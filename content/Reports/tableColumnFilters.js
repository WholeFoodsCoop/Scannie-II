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

var hideChecked = false;
$('th').keyup(function(e){
    var str = $(this).text();
    str = str.toUpperCase();
    var columnName = $(this).attr('data-columnName');
    var i = $(this).attr('data-i');
    $('tr').each(function(){
        var checked = $(this).find('[type=checkbox]').prop('checked')?true:false;
        if (hideChecked == true) {
            if (!checked) {
                $(this).show();
            }
        } else {
            $(this).show();
        }
    });
    $('tr td:nth-child('+i+')').each(function(){
        var checked = $(this).find('[type=checkbox]').prop('checked')?true:false;
        var curtext = $(this).text();
        curtext = curtext.toUpperCase();
        if (!curtext.includes(str)) {
            if (!checked) {
                $(this).closest('tr').hide();
            }
        }
    });
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
            var checked = $(this).find('[type=checkbox]').prop('checked')?true:false;
            if (hideChecked == true) {
                if (!checked) {
                    $(this).show();
                }
            } else {
                $(this).show();
            }
        });
    }
});

$('#upcBtnOppo').click(function(){
    var active = $(this).hasClass('active');
    if (active == true) {
        $(this).removeClass('active').addClass('inactive');
        hideChecked = true;
        $('td').each(function(){
            var checked = $(this).find('[type=checkbox]').prop('checked')?true:false;
            if (checked == true) {
                $(this).closest('tr').hide();
            }
        });
    } else {
        $(this).removeClass('inactive').addClass('active');
        hideChecked = false;
        $('tr').each(function(){
            $(this).show();
        });
    }
});

$('#upcBtnAll').click(function(){
    hideChecked = false;
    $('#upcBtnOppo').removeClass('inactive').addClass('active');
});

$('tr').each(function(){
    $(this).find('td:first-child').click(function(){
        $(this).closest('tr').find("input").each(function() {
            $(this).trigger('click');
        });
    });
});
