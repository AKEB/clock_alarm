<?php
require_once('config.php');
require_once('functions.php');

$sounds = glob('sounds/*.mp3');
foreach ($sounds as $k => $file) {
	$sounds[$k] = basename($file, '.mp3');
}

?>
<!doctype html>
<html lang="ru" data-bs-theme="dark">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Умный Будильник</title>
		<meta name="author" lang="en" content="Vadim Babadzhanyan" />
		<meta name="copyright" content="Vadim Babadzhanyan" />
		<meta name="Author" lang="ru" content="Вадим Бабаджанян" />

		<link rel="shortcut icon" href="/images/favicon.ico" type="image/x-icon" />
		<link rel="apple-touch-icon" sizes="57x57" href="/images/apple-icon-57x57.png">
		<link rel="apple-touch-icon" sizes="60x60" href="/images/apple-icon-60x60.png">
		<link rel="apple-touch-icon" sizes="72x72" href="/images/apple-icon-72x72.png">
		<link rel="apple-touch-icon" sizes="76x76" href="/images/apple-icon-76x76.png">
		<link rel="apple-touch-icon" sizes="114x114" href="/images/apple-icon-114x114.png">
		<link rel="apple-touch-icon" sizes="120x120" href="/images/apple-icon-120x120.png">
		<link rel="apple-touch-icon" sizes="144x144" href="/images/apple-icon-144x144.png">
		<link rel="apple-touch-icon" sizes="152x152" href="/images/apple-icon-152x152.png">
		<link rel="apple-touch-icon" sizes="180x180" href="/images/apple-icon-180x180.png">
		<link rel="icon" type="image/png" sizes="192x192"  href="/images/android-icon-192x192.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
		<meta name="msapplication-TileColor" content="#ffffff">
		<meta name="msapplication-TileImage" content="/images/ms-icon-144x144.png">
		<meta name="theme-color" content="#ffffff">

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

		<div class="modal fade" id="alarmModal" tabindex="-1" index="">
			<div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
				<div class="modal-content">
					<div class="modal-header">
						<div class="row w-100 p-0 m-0">
							<div class="col">
								<button type="button" class="cancel-button btn btn-text btn-sm text-primary" data-bs-dismiss="modal">Отменить</button>
							</div>
							<div class="col-auto">
								<h5 class="modal-title">Будильник</h5>
							</div>
							<div class="col">
								<button type="button" class="save-button btn btn-text btn-sm text-primary">Сохранить</button>
							</div>
						</div>
					</div>
					<div class="modal-body">
						<div class="row">
							<div class="col">
								<select class="form-select form-select-lg mb-3" id="alarm-hour">
									<?php
									for ($i = 0; $i < 24; $i++) {
										echo '<option value="'.$i.'">'.sprintf("%02d",$i).'</option>';
									}
									?>
								</select>
							</div>
							<div class="col">
								<select class="form-select form-select-lg mb-3" id="alarm-minute">
									<?php
									for ($i = 0; $i < 60; $i++) {
										echo '<option value="'.$i.'">'.sprintf("%02d",$i).'</option>';
									}
									?>
								</select>
							</div>
						</div>
						<hr/>
						<div class="row w-100">
							<label for="alarm-sound" class="col-sm-2 col-form-label">Повторять</label>
							<div class="col w-100">
								<?php
								$weeks = [1 => 'Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
								foreach ($weeks as $k => $v) {
									?>
									<div class="form-check form-lg-check form-check-inline">
										<input class="form-check-input week" type="checkbox" id="week_<?=$k;?>" value="<?=$k;?>">
										<label class="form-check-label week" for="week_<?=$k;?>"><?=$v;?></label>
									</div>
									<?php
								}
								?>
							</div>
						</div>
						<hr/>
						<div class="row">
							<div class="col">
								<label for="alarm-sound" class="col-sm-2 col-form-label">Мелодия</label>
								<select class="form-select form-select-lg mb-3" id="alarm-sound" >
									<?php
									foreach ($sounds as $k => $v) {
										echo '<option value="'.$v.'">'.$v.'</option>';
									}
									?>
								</select>
							</div>
						</div>
						<div class="row">
							<div class="col">
								<audio controls src="sounds/default.mp3" id="alarm-sound-player"></audio>
							</div>
						</div>
						<div class="row">
							<div class="col">
								<label for="customRange2" class="form-label">Громкость: <span id="alarm-volume-value">70</span></label>
								<input type="range" class="form-range" min="0" max="100" step="5" id="alarm-volume">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="delete-button btn btn-text btn-sm text-danger">Удалить будильник</button>
					</div>
				</div>
			</div>
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

				$('body').delegate('.delete-button', 'click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					click_delete_button($('#alarmModal').attr('index'));
				});

				$('body').delegate('.save-button', 'click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					click_save_button();
				});


				$('body').delegate('.edit_button', 'click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					click_edit_button($(this).attr('id'));
				});

				$('body').delegate('#alarm-volume', 'input', function(e) {
					$('#alarm-volume-value').html($('#alarm-volume').val());
				});

				$('body').delegate('#alarm-volume', 'change', function(e) {
					alarm_sound_volume_change();
				});

				$('body').delegate('#alarm-sound', 'change', function(e) {
					alarm_sound_change();
				});


				refresh_database();

			});
		</script>
		<script src="/js/main.js?atime=<?=fileatime('js/main.js');?>"></script>
		<script src="/js/bootstrap.bundle.min.js?atime=<?=fileatime('js/bootstrap.bundle.min.js');?>"></script>
	</body>
</html>
