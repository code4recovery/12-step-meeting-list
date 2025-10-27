<?php
/**
 * Dev-only WP-CLI command to seed TSML from template.csv without touching editors or external HTTP.
 * The intent was to reuse the existing tsml_import_data_source function, but it seems there would need
 * to be changes to make it work. Even falling back to the other tsml_import_* functions wasn't straightforward
 * due to extensive use of global structs. ChatGPT analyzed the dependencies and hydrated globals so it works,
 * but if anyone has a simpler way please update or make suggestions.
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	add_action( 'plugins_loaded', function () {

		$required = [
			'tsml_import_sanitize_meetings',
			'tsml_import_buffer_set',
			'tsml_import_buffer_next',
		];
		foreach ( $required as $fn ) {
			if ( ! function_exists( $fn ) ) {
				WP_CLI::warning( "{$fn}() not found — is the 12 Step Meeting List plugin active?" );
			}
		}

		class TSML_Dev_Import_CLI {

			private function ensure_tsml_state(): void {
				global $tsml_data_sources, $tsml_google_overrides, $tsml_debug;

				if ( ! is_array( $tsml_data_sources ) ) {
					$tsml_data_sources = get_option( 'tsml_data_sources' );
					if ( ! is_array( $tsml_data_sources ) ) {
						$tsml_data_sources = [];
					}
				}

				if ( ! is_array( $tsml_google_overrides ) ) {
					$tsml_google_overrides = get_option( 'tsml_google_overrides' );
					if ( ! is_array( $tsml_google_overrides ) ) {
						$tsml_google_overrides = [];
					}
				}

				if ( ! isset( $tsml_debug ) ) {
					$tsml_debug = false;
				}
			}

			private function ensure_tsml_import_globals(): void {
				global $tsml_programs, $tsml_program, $tsml_days,
					$tsml_meeting_attendance_options,
					$tsml_contact_fields, $tsml_entity_fields,
					$tsml_array_fields, $tsml_source_fields_map,
					$tsml_import_fields;

				if ( ! defined( 'TSML_GROUP_CONTACT_COUNT' ) ) {
					define( 'TSML_GROUP_CONTACT_COUNT', 3 );
				}

				if ( ! is_string( $tsml_program ) || $tsml_program === '' ) {
					$tsml_program = 'aa';
				}

				if ( ! is_array( $tsml_programs ) || empty( $tsml_programs[ $tsml_program ]['types'] ) ) {
					$tsml_programs = [
						'aa' => [ 'types' => [
							'ASL' => 'ASL','B' => 'B','C' => 'C','CF' => 'CF','D' => 'D','G' => 'G','GR' => 'GR',
							'L' => 'L','M' => 'M','MED' => 'MED','O' => 'O','OUT' => 'OUT','SP' => 'SP',
							'ST' => 'ST','T' => 'T','W' => 'W','X' => 'X','YP' => 'YP','ONL' => 'ONL',
						]],
					];
				}

				if ( ! is_array( $tsml_days ) ) {
					$tsml_days = [
						0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday',
						3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
					];
				}

				if ( ! is_array( $tsml_meeting_attendance_options ) ) {
					$tsml_meeting_attendance_options = [ 'in_person' => 'in_person', 'online' => 'online', 'hybrid' => 'hybrid' ];
				}

				if ( ! is_array( $tsml_contact_fields ) ) {
					$tsml_contact_fields = [
						'website' => 'url',
						'email'   => 'email',
						'phone'   => 'phone',
					];
				}

				if ( ! is_array( $tsml_entity_fields ) ) {
					$tsml_entity_fields = [];
				}
				if ( ! is_array( $tsml_array_fields ) ) {
					$tsml_array_fields = [ 'feedback_emails' ];
				}

				if ( ! is_array( $tsml_source_fields_map ) ) {
					$tsml_source_fields_map = [
						'formatted_address' => 'formatted_address',
						'region'            => 'region',
						'sub_region'        => 'sub_region',
						'slug'              => 'slug',
					];
				}

				if ( ! is_array( $tsml_import_fields ) ) {
					$tsml_import_fields = [];
				}
			}

			private function read_template_csv(): array {
				$plugin_dir = WP_PLUGIN_DIR . '/12-step-meeting-list';
				$template   = $plugin_dir . '/template.csv';

				if ( ! file_exists( $template ) ) {
					WP_CLI::error( 'template.csv not found in plugin root.' );
				}

				$fh = fopen( $template, 'r' );
				if ( ! $fh ) {
					WP_CLI::error( 'Unable to open template.csv.' );
				}

				$rows = [];
				while ( ( $line = fgetcsv( $fh ) ) !== false ) {
					$rows[] = $line;
				}
				fclose( $fh );

				if ( empty( $rows ) ) {
					return [];
				}

				$header = array_map(
					static function( $h ) {
						$h = sanitize_title_with_dashes( (string) $h );
						return str_replace( '-', '_', $h );
					},
					array_shift( $rows )
				);

				$out = [];
				foreach ( $rows as $i => $cols ) {
					$item = [];
					foreach ( $header as $idx => $key ) {
						$item[ $key ] = isset( $cols[ $idx ] ) ? trim( (string) $cols[ $idx ] ) : '';
					}
					$out[] = $item;
				}

				return $out;
			}

			private function ensure_wp_timezone(): void {
				$tz = get_option( 'timezone_string' );
				if ( ! is_string( $tz ) || $tz === '' ) {
					update_option( 'timezone_string', 'America/Los_Angeles' );
				}
			}

			private function maybe_clear_existing( bool $force ): int {
				if ( ! $force ) {
					return 0;
				}
				$q = new WP_Query( [
					'post_type'      => 'tsml_meeting',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				] );

				$count = 0;
				foreach ( $q->posts as $pid ) {
					wp_delete_post( $pid, true );
					$count++;
				}
				return $count;
			}

			public function __invoke( $args, $assoc_args ) {

				$this->ensure_wp_timezone();
				$this->ensure_tsml_state();
				$this->ensure_tsml_import_globals();

				$existing = (int) ( wp_count_posts( 'tsml_meeting' )->publish ?? 0 );
				$force    = isset( $assoc_args['force'] );

				if ( ! $force && $existing > 0 ) {
					WP_CLI::success( "Meetings already present ({$existing}); skipping import." );
					return;
				}

				$cleared = $this->maybe_clear_existing( $force );
				WP_CLI::log( "Cleared {$cleared} existing tsml_meeting posts." );

				$raw = $this->read_template_csv();
				WP_CLI::log( 'Parsed ' . count( $raw ) . ' rows from template.csv.' );
				if ( ! empty( $raw ) ) {
					$sample = $raw[0];
					$sample_name = $sample['name'] ?? '';
					$sample_loc  = $sample['location'] ?? '';
					$sample_addr = trim( ( $sample['formatted_address'] ?? '' ) ?: ( ( $sample['address'] ?? '' ) . ', ' . ( $sample['city'] ?? '' ) . ', ' . ( $sample['state'] ?? '' ) . ' ' . ( $sample['postal_code'] ?? '' ) ) );
					$sample_day  = $sample['day'] ?? '';
					$sample_time = $sample['time'] ?? '';
					WP_CLI::log( "Sample row => name: {$sample_name} | location: {$sample_loc} | addr: {$sample_addr} | day: {$sample_day} | time: {$sample_time}" );
				}

				$this->ensure_tsml_state();
				$this->ensure_tsml_import_globals();

				// ✅ TEMPORARY: suppress specific plugin warnings
				set_error_handler(function($errno, $errstr) {
					if (str_contains($errstr, 'file_put_contents') || str_contains($errstr, 'Undefined array key')) {
						return true; // ignore those
					}
					return false;
				});

				$meetings = tsml_import_sanitize_meetings( $raw, 'dev://tsml-seed', 0 );
				tsml_import_buffer_set( $meetings, 'dev://tsml-seed', 0 );

				do {
					$result    = tsml_import_buffer_next( 50 );
					$remaining = (int) ( $result['remaining'] ?? 0 );
				} while ( $remaining > 0 );

				restore_error_handler();

				$total = (int) ( wp_count_posts( 'tsml_meeting' )->publish ?? 0 );
				WP_CLI::success( "Import complete. Meetings now in DB: {$total}." );
			}
		}

		WP_CLI::add_command( 'tsml-dev import', 'TSML_Dev_Import_CLI' );
	} );
}
