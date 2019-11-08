$('table').each(function(){
    var tableID = $(this).attr('id');
    var i = 1;
    $('th').each(function(){
        var columnName = $(this).text();
        $(this).attr('data-columnName', columnName)
            .attr('data-tableID', tableID)
            .attr('data-i', i);
        $(this).attr('contentEditable', true);
        i++;
    });
});

$('th').keyup(function(e){
    var start_time = new Date();
    start_time = start_time.getTime();
    var str = $(this).text();
    str = str.toUpperCase();
    var columnName = $(this).attr('data-columnName');
    var i = $(this).attr('data-i');
    $('tr td:nth-child('+i+')').each(function(){
        var curtext = $(this).text();
        curtext = curtext.toUpperCase();
        if (!curtext.includes(str)) {
            $(this).closest('tr').hide();
        }
    });
    var stop_time = new Date();
    stop_time = stop_time.getTime();
    var time_diff = stop_time - start_time;
    console.log('filter run-time: '+time_diff+' milliseconds');
});
$('th').focus(function(){
    $(this).text("");
});
$('th').focusout(function(){
    var columnName = $(this).attr('data-columnName');
    var str = $(this).text();
    if (str == '') {
        $(this).text(columnName);    
        $('tr').each(function(){
            $(this).show();
        });
    }
});
