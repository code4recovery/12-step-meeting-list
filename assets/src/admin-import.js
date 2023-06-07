
//called from admin_import.php
function toggle_import_source(selected) {
	if (selected == 'csv') {
		document.getElementById('dv_file_source').style.display = 'block';
		document.getElementById('dv_data_source').style.display = 'none';
	} else {
		document.getElementById('dv_file_source').style.display = 'none';
		document.getElementById('dv_data_source').style.display = 'block';
	}
}