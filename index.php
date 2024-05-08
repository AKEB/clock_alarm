<?php
require_once('config.php');
require_once('functions.php');

$alarms = read_database();

// var_export($alarms);

// $param = [
// 	[
// 		'hour' => 9,
// 		'minute' => 0,
// 		'sound' => 'example.mp3',
// 		'month' => 5,
// 		'date' => 8,
// 	],
// 	[
// 		'hour' => 8,
// 		'minute' => 45,
// 		'sound' => 'example.mp3',
// 		'repeat' => [
// 			0 => true,
// 			1 => true,
// 			2 => true,
// 			3 => true,
// 			4 => true,
// 			5 => false,
// 			6 => false,
// 		],
// 	]
// ];

// write_database($param);
?>
<!doctype html>
<html lang="ru">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Bootstrap demo</title>
		<link href="/bootstrap.min.css?atime=<?=fileatime('bootstrap.min.css');?>" rel="stylesheet">
		<link href="/main.css?atime=<?=fileatime('main.css');?>" rel="stylesheet">
	</head>
	<body>
		<div class="container">
			<h1>Мои будильники</h1>

			<?php
			foreach ($alarms as $k=>$alarm) {
				?>
				<div class="card">
					<div class="card-body">
						<div class="row">
							<div class="col col-xs-7 col-sm-7 col-md-8 col-lg-8 col-xl-9 col-xxl-10">
								<div class="time"><?php printf("%02d:%02d", intval($alarm['hour']), intval($alarm['minute'])); ?></div>
							</div>
							<div class="col">
								<div class="form-check form-switch form-check-reverse" style="font-size: 50pt;">
									<input class="form-check-input" type="checkbox" id="flexSwitchCheckReverse" <?=isset($alarm['status']) && $alarm['status'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="flexSwitchCheckReverse"></label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col">
								<?php
								if (isset($alarm['repeat'])) {
									echo alarm_get_repeat_text($alarm['repeat']);
								} else {
									$time = mktime($alarm['hour'], $alarm['minute'], 0, $alarm['month'], $alarm['date'], date("Y"));
									echo alarm_get_onetime_text($time);
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<?php
			}
			?>

		</div>
		<script src="/main.js?atime=<?=fileatime('main.js');?>"></script>
		<script src="/bootstrap.bundle.min.js?atime=<?=fileatime('bootstrap.bundle.min.js');?>"></script>
	</body>
</html>
