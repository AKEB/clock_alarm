
function click_plus_button() {
  console.log('click_plus_button');
}

function click_edit_button(id) {
  console.log('click_edit_button id='+id);
}

function get_database_hash() {
  $.ajax({
    type: "GET",
    url : "/get_database_hash.php?t="+Math.round((new Date()).getTime() / 1000),
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
    url : "/get_database.php?t="+Math.round((new Date()).getTime() / 1000),
    cache: false,
    dataType: "json",
    data : {
      password : password
    },
    success : function(alarms) {
      print_alarms(alarms)
    },
    error : function(jqXHR, textStatus, errorThrown) {
      console.error(textStatus+' '+errorThrown);
    }
  });
}

function print_alarms(alarms) {
  for (var i=0; i<alarms.length; i++) {
    let alarm = alarms[i];
    print_alarm(i, alarm);
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
  // setInterval(function(){
    old_database_hash = database_hash
    get_database_hash();
    if (old_database_hash != database_hash) {
      get_database();
    }
  // }, 1000);
  setTimeout(refresh_database, 500);
}

function status_change_button_click(index, value) {
  console.log('status_change_button_click index='+index+' value='+value);
  $.ajax({
    type: "POST",
    url : "/update_database.php?t="+Math.round((new Date()).getTime() / 1000),
    cache: false,
    dataType: "json",
    data : {
      password : password,
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
