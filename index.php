<?php
require_once('lib/common.php');

$session = Session::check();

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

  <link rel="manifest" href="/manifest.json" />
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
			<h1 class="display-3">Будильники <?php if ($session) { ?><span class="plus_button"><i class="bi bi-plus-square"></i></span><?php } ?></h1>
			<?php
			if ($session) {
				?>
				<div class="alarms"></div>
				<?php
			} else {
				?>
				<form method="POST">
					<div class="mb-3">
						<label for="login" class="form-label">Username</label>
						<input type="login" class="form-control" name="login" id="login" placeholder="">
					</div>
					<div class="mb-3">
						<label for="password" class="form-label">Password</label>
						<input type="password" id="password" name="password" class="form-control">
					</div>
					<button type="submit" class="btn btn-primary" name="sign_in" value="1">Sign in</button>
				</form>
				<?php
			}
			?>
		</div>
		<?php
		if ($session) {
			?>
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
							<div class="row w-100 mb-3">
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
							<div class="row w-100 mb-3">
								<label for="alarm-sound" class="col-sm-3 col-form-label">Повторять</label>
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
							<div class="row w-100 mb-3">
								<div class="col">
									<audio src="sounds/default.mp3" id="alarm-sound-player"></audio>
									<label for="alarm-sound" class="col-sm-2 col-form-label">Мелодия</label>
									<div class="input-group">
										<select class="form-select" id="alarm-sound" >
											<?php
											foreach ($sounds as $k => $v) {
												echo '<option value="'.$v.'">'.$v.'</option>';
											}
											?>
										</select>
										<button class="btn btn-outline-secondary" type="button" id="alarm-sound-play-button"><i class="bi bi-play-fill"></i></button>
										<button class="btn btn-outline-secondary" type="button" id="alarm-sound-pause-button" style="display:none;"><i class="bi bi-pause-fill"></i></button>
										<button class="btn btn-outline-secondary" type="button" id="alarm-sound-volume-down-button"><i class="bi bi-volume-down-fill"></i></button>
										<button class="btn btn-outline-secondary" type="button" id="alarm-sound-volume-up-button"><i class="bi bi-volume-up-fill"></i></button>
									</div>

								</div>
							</div>
							<div class="row w-100 mb-3">
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
			</script>
			<script src="/js/main.js?atime=<?=fileatime('js/main.js');?>"></script>
			<?php
		}
		?>
		<script src="/js/bootstrap.bundle.min.js?atime=<?=fileatime('js/bootstrap.bundle.min.js');?>"></script>
	</body>
</html>
