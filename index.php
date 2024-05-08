<?php
require_once('config.php');
require_once('functions.php');

?>
<!doctype html>
<html lang="ru" data-bs-theme="dark">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Умный Будильник</title>
		<link href="/css/bootstrap.min.css?atime=<?=fileatime('css/bootstrap.min.css');?>" rel="stylesheet">
		<link href="/css/main.css?atime=<?=fileatime('css/main.css');?>" rel="stylesheet">
		<link href="/icons/font/bootstrap-icons.min.css?atime=<?=fileatime('icons/font/bootstrap-icons.min.css');?>" rel="stylesheet">
		<script src="/js/jquery-3.7.1.min.js?atime=<?=fileatime('js/jquery-3.7.1.min.js');?>"></script>
	</head>
	<body>
		<div class="container">
			<h1 class="display-3">Мои будильники <span class="plus_button"><i class="bi bi-plus-square"></i></span></h1>
			<div class="alarms"></div>
		</div>
		<script>
			var database_hash = null;
			var password = '<?=constant('PASSWORD');?>';
			$(document).ready(function() {

				$('.plus_button').click(function(e) {
					e.preventDefault();
					click_plus_button();
				});

				$('body').delegate('.status_change_button', 'change', function(e) {
					e.preventDefault();
					e.stopPropagation();
					status_change_button_click($(this).attr('index'), $(this).prop('checked'));
				});

				$('.status_change_button').change(function(e) {

				});

				$('.edit_button').click(function(e) {
					e.preventDefault();
					e.stopPropagation();
					click_edit_button($(this).attr('id'));
				});

				refresh_database();

			});
		</script>
		<script src="/js/main.js?atime=<?=fileatime('js/main.js');?>"></script>
		<script src="/js/bootstrap.bundle.min.js?atime=<?=fileatime('js/bootstrap.bundle.min.js');?>"></script>
	</body>
</html>
