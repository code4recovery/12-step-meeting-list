<?php
/**
 * Creates the Blocks.
 *
 * This class is instantiated and run() in the main 12-step-meeting-list.php file to create all blocks that are
 * available within the plugin
 *
 * @author Code for Recovery
 * @version 1.0.0
 * @since 1.0.0
 * @package Code4Recovery\TSML
 */

namespace Code4Recovery\TSML;

if (! class_exists('Blocks')) {
    class Blocks
    {
        /**
         * Sets this class into motion.
         *
         * Executes the plugin by calling the run method of classes.
         *
         * @return void
         */
        public function run(): void
        {
            add_action('init', [ $this, 'setupBlocks' ]);
        }

        /**
         * Register the blocks.
         *
         * @return void
         */
        public function setupBlocks(): void
        {
            register_block_type(
                TSML_PATH . 'assets/build/blocks'
            );
        }
    }
}
