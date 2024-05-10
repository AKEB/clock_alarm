var CLOCK_ALARMS = [];

function click_plus_button() {
  console.log('click_plus_button');

  $('.modal-footer').hide();
  $('.modal-title').html('Новый');

  const date = new Date();
  const hour = date.getHours();
  const minute = date.getMinutes();

  $('#alarm-hour option[value="' + hour + '"]').prop('selected', true)
  $('#alarm-minute option[value="' + minute + '"]').prop('selected', true)
  $('input.week').prop('checked', false);
  $('#alarm-sound option[value="default"]').prop('selected', true).change();

  $('#alarm-volume').val(70);
  $('#alarm-volume-value').html(70);

  var myModal = $('#alarmModal');
  myModal.attr('index', '');
  myModal.modal('show');
}

function click_edit_button(id) {
  console.log('click_edit_button id='+id);
  var alarm = CLOCK_ALARMS[id];
  console.log(alarm);
  $('.modal-footer').show();
  $('.modal-title').html('Будильник');

  $('#alarm-hour option[value="' + alarm['hour'] + '"]').prop('selected', true)
  $('#alarm-minute option[value="' + alarm['minute'] + '"]').prop('selected', true)
  $('input.week').prop('checked', false);
  if (alarm['repeat']) {
    for (var i = 0; i < alarm['repeat'].length; i++) {
      if (alarm['repeat'][i]) $('input.week#week_'+(i+1)).prop('checked', true);
    }
  }

  $('#alarm-sound option[value="'+alarm['sound']+'"]').prop('selected', true).change();
  $('#alarm-volume').val(alarm['volume'] ? alarm['volume'] : 70);
  $('#alarm-volume-value').html(alarm['volume'] ? alarm['volume'] : 70);

  var myModal = $('#alarmModal');
  myModal.attr('index', id);
  myModal.modal('show');
}

function alarm_sound_change() {
  console.log('alarm_sound_change');
  var audio = $('#alarm-sound-player');
  document.getElementById('alarm-sound-player').pause();
  $('#alarm-sound-play-button').show();
  $('#alarm-sound-pause-button').hide();
  audio.attr('src', '/sounds/' + $('#alarm-sound').val() + '.mp3');
}

function click_save_button() {
  console.log('click_save_button');

  document.getElementById('alarm-sound-player').pause();
  $('#alarm-sound-play-button').show();
  $('#alarm-sound-pause-button').hide();

  $.ajax({
    type: "POST",
    url : "/update_database.php",
    cache: false,
    dataType: "json",
    data : {
      hour: $('#alarm-hour').val(),
      minute: $('#alarm-minute').val(),
      sound: $('#alarm-sound').val(),
      volume: $('#alarm-volume').val(),
      repeat: [
        $('input.week#week_1').prop('checked'),
        $('input.week#week_2').prop('checked'),
        $('input.week#week_3').prop('checked'),
        $('input.week#week_4').prop('checked'),
        $('input.week#week_5').prop('checked'),
        $('input.week#week_6').prop('checked'),
        $('input.week#week_7').prop('checked')
      ],
      index: $('#alarmModal').attr('index'),
      action: $('#alarmModal').attr('index')? "edit" : "add"
    },
    success : function(alarms) {
      var myModal = $('#alarmModal');
      myModal.attr('index', '');
      myModal.modal('hide');
      print_alarms(alarms)
    },
    error : function(jqXHR, textStatus, errorThrown) {
      console.error(textStatus+' '+errorThrown);
    }
  });
}

function click_delete_button(index) {
  console.log('click_delete_button index='+index);

  document.getElementById('alarm-sound-player').pause();
  $('#alarm-sound-play-button').show();
  $('#alarm-sound-pause-button').hide();

  $.ajax({
    type: "POST",
    url : "/update_database.php",
    cache: false,
    dataType: "json",
    data : {
      index: index,
      action:"delete"
    },
    success : function(alarms) {
      var myModal = $('#alarmModal');
      myModal.attr('index', '');
      myModal.modal('hide');
      print_alarms(alarms)
    },
    error : function(jqXHR, textStatus, errorThrown) {
      console.error(textStatus+' '+errorThrown);
    }
  });
}

function get_database_hash() {
  $.ajax({
    type: "GET",
    url : "/get_database_hash.txt",
    cache: false,
    async: false,
    dataType: "text",
    success : function(d) {
      database_hash = d;
    },
    error : function(jqXHR, textStatus, errorThrown) {
      console.error(textStatus+' '+errorThrown);
    }
  });
}

function get_database() {
  $.ajax({
    type: "POST",
    url : "/get_database.php",
    cache: false,
    dataType: "json",
    data : {},
    success : function(alarms) {
      CLOCK_ALARMS = alarms;
      print_alarms(alarms)
    },
    error : function(jqXHR, textStatus, errorThrown) {
      CLOCK_ALARMS = [];
      console.error(textStatus+' '+errorThrown);
    }
  });
}

function print_alarms(alarms) {

  for (var i=0; i<alarms.length; i++) {
    let alarm = alarms[i];
    print_alarm(i, alarm);
  }

  var children = $('.alarms').children();
  for (var i=alarms.length; i<children.length; i++) {
    children[i].remove();
  }
}

function pad(num, size) {
  num = num.toString();
  while (num.length < size) num = "0" + num;
  return num;
}

function print_alarm(i, alarm) {
  console.log(i, alarm);
  var object = $('.alarms>.card#alarm_'+i)[0];
  if (!object || object.length == 0) {
    var time = '<div class="row edit_button" id="'+i+'"><div class="col"><div class="time" id="alarm_'+i+'">'+pad(alarm.hour, 2)+':'+pad(alarm.minute, 2)+'</div></div></div>';
    var days = '<div class="row edit_button" id="'+i+'"><div class="col days" id="alarm_'+i+'">'+alarm.repeat_text+'</div></div>';
    var checkbox = '<div class="form-check form-switch form-check-reverse checkbox"><input index="'+i+'" class="form-check-input status_change_button" type="checkbox" id="alarm_'+i+'" '+(alarm.status ? 'checked' : '')+'></div>';

    $('.alarms').append('<div class="alarm card" id="alarm_'+i+'"><div class="card-body">' + time + days + checkbox + '</div></div>');
    var object = $('.alarms>.card#alarm_'+i)[0];
  } else {
    $('.days#alarm_'+i).html(alarm.repeat_text);
    $('.time#alarm_'+i).html(pad(alarm.hour, 2)+':'+pad(alarm.minute, 2));
    $('input#alarm_'+i).prop('checked', alarm.status);
  }

}


function refresh_database() {
  old_database_hash = database_hash
  get_database_hash();
  if (old_database_hash != database_hash) {
    get_database();
  }
  setTimeout(refresh_database, 1000);
}

function status_change_button_click(index, value) {
  console.log('status_change_button_click index='+index+' value='+value);
  $.ajax({
    type: "POST",
    url : "/update_database.php",
    cache: false,
    dataType: "json",
    data : {
      index: index,
      action:"change_status",
      status: value ? 1 : 0,
    },
    success : function(alarms) {
      print_alarms(alarms)
    },
    error : function(jqXHR, textStatus, errorThrown) {
      console.error(textStatus+' '+errorThrown);
    }
  });
}

function alarm_sound_volume_change() {
  console.log('alarm_sound_volume_change');
  $.ajax({
    type: "POST",
    url : "/update_database.php",
    cache: false,
    async: true,
    dataType: "json",
    data : {
      volume: $('#alarm-volume').val(),
      action: "change_sound_test",
    }
  });
}


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

  $('body').delegate('.cancel-button', 'click', function(e) {
    document.getElementById('alarm-sound-player').pause();
    $('#alarm-sound-play-button').show();
    $('#alarm-sound-pause-button').hide();
  });

  $('body').delegate('#alarm-sound-play-button', 'click', function(e) {
    document.getElementById('alarm-sound-player').play();
    $('#alarm-sound-play-button').hide();
    $('#alarm-sound-pause-button').show();
  });

  $('body').delegate('#alarm-sound-pause-button', 'click', function(e) {
    document.getElementById('alarm-sound-player').pause();
    $('#alarm-sound-play-button').show();
    $('#alarm-sound-pause-button').hide();
  });

  $('body').delegate('#alarm-sound-volume-up-button', 'click', function(e) {
    document.getElementById('alarm-sound-player').volume += 0.1
  });

  $('body').delegate('#alarm-sound-volume-down-button', 'click', function(e) {
    document.getElementById('alarm-sound-player').volume -= 0.1
  });

  refresh_database();

});
