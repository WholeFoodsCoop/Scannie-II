// dynamically add stipe to table
//var stripeTable = function(){
//    $('tr.mytable').each(function(){
//        $(this).removeClass('stripe');
//    });
//    $('tr.mytable').each(function(i = 0){
//        if ($(this).is(':visible')) {;
//            if (i % 2 == 0) {
//                $(this).addClass('stripe');
//            } else {
//                $(this).removeClass('stripe');
//            }
//        i++;
//        }
//    });
//
//    return false;
//};

var hideOld = function()
{
    var thisBtn = $('#hide-old-btn');
    var text = thisBtn.text();
    if (thisBtn.hasClass('inactive')) {
        $('tr[data-age]').each(function(){
            if ($(this).attr('data-age') == 'old' || $(this).attr('data-age') == 'ancient') {
                $(this).hide();
            };
        });
        thisBtn.removeClass('inactive')
            .addClass('active');
        text = text.replace('Hide', 'Show');
    } else {
        $('tr[data-age]').each(function(){
            if ($(this).attr('data-age') == 'old' || $(this).attr('data-age') == 'ancient') {
                $(this).show();
            };
        });
        thisBtn.removeClass('active')
            .addClass('inactive');
        text = text.replace('Show', 'Hide');
    }
    thisBtn.text(text);
    stripeTable();
}

// click or touch any cell with data-note
$('td[data-note]').click(function(){
    var note = $(this).attr('data-note');
    if (note != '') {
        alert('NOTE: '+note);
    }
});
$('td[data-extra]').click(function(){
    var extra = $(this).attr('data-extra');
    alert(extra);
});

// add sale 'age' to tr based on most recent sale dates
$('tr[data-lastsold]').each(function(){
    var lastsold = $(this).attr('data-lastsold');
    var tdate = new Date(lastsold);
    var year = tdate.getFullYear();
    var month = tdate.getMonth()+1;
    var day = tdate.getDate()+1;

    var check1 = new Date();
    check1.setMonth(check1.getMonth() - 2);
    var cy = check1.getFullYear();
    var cm = check1.getMonth()+1;
    var cd = check1.getDate()+1;
    
    var check2 = new Date();
    check2.setMonth(check2.getMonth() - 12);
    var cy = check2.getFullYear();
    var cm = check2.getMonth()+1;
    var cd = check2.getDate()+1;

    var date1 = year+'-'+month+'-'+day;
    var date2 = cy+'-'+cm+'-'+cd;

    year = parseInt(year,10);
    cy = parseInt(cy,10);
    if (tdate < check2) {
        $(this).find('td:first-child').css('background', 'tomato');
        $(this).attr('data-age', 'ancient');
    } else if (tdate < check1) {
        $(this).find('td:first-child').css('background', 'yellow');
        $(this).attr('data-age', 'old');
    }
});

var queue_names = {'98':'DNC', '11':'Ed', '10':'SL', '9':'DISC', '8':'TWOUP', '7':'FOURUP', 
    '6':'TWELVEUP', '5':'ST', '4':'Add', '2':'Miss', '1':'Good'};
$('#queue').change(function(){
    document.forms['queue-form'].submit();
});
$('#session').change(function(){
    document.forms['queue-form'].submit();
});
var unchecked = $('#unchecked_queue').val();
if (unchecked != undefined) {
    $('#product-list tr').each(function(){
        var queue_list = $(this).find('.queue-list').text();
        if (queue_list.includes('1') || queue_list.includes('2')) {
            $(this).closest('tr').hide();
        } else {
        }
    });
    $('.tc-clear').each(function(){
        $(this).hide();
    });
} else {
    // do this to all queues that are not 1
    $('.tc-unch').each(function(){
        $(this).hide();
    });
}
/*
    replace numbers with names
*/
var queue_list = '';
$('#product-list tr').each(function(){
    var cur_tr = $(this);
    for (var i=99; i>0; i--) {
        if (queue_names[i] != undefined) {
            queue_list = cur_tr.find('.queue-list').text();
            var replace_str = queue_list.replace(new RegExp(i, 'gi'), queue_names[i]);
            cur_tr.find('td.queue-list').text(replace_str);
        };
    };
    var num_rpl = {'TWELVE':12, 'FOUR':4, 'TWO':2};
    $.each(num_rpl, function(k, v){
        queue_list = cur_tr.find('.queue-list').text();
        var replace_str = queue_list.replace(new RegExp(k, 'gi'), v);
        cur_tr.find('td.queue-list').text(replace_str);
    });
});
$(document).ready(function(){
    $('#loading').hide();
});

$("table").bind('sortEnd', function(){
    console.log('hi');
    stripeTable();
});
//end of new.js
/*
    old batchCheckQueues.js
*/
// sort & hide columns
$(function(){
    $('.col-hide').click(function(){
        var colName = $(this).val();
        var filterBtnID = '#col-filter-'+colName;
        $('.col-'+colName).hide();
        $(filterBtnID).show();
    });
    stripeTable();
});
$('.col-filter').click(function(){
    var colName = $(this).text(); 
    $('.col-'+colName).show();
    $(this).hide();
});

// queue button events
$('.queue-btn').click(function(){
    var qv = $(this).val();
    var queueName = $(this).text();
    var id = $(this).attr('id');
    var upc = id.substring(5);
    var closestTr = $(this).closest('tr');
    var sessionName = $('#sessionName').val();
    var storeID = $('#storeID').val();
    $.ajax({
        type: 'post',
        url: 'SCS.php',
        data: 'upc='+upc+'&queue='+queueName+'&qval='+qv+'&sessionName='+sessionName+'&storeID='+storeID,
        dataType: 'json',
        success: function(json) {
            //alert('success');
            if (qv == 0) {
                closestTr.css('background-color','white');
            } else {
                closestTr.css('background-color', queueNamesToColors[queueName]);
            }
            if (json.error) {
                alert(json.error);
            }
        }
    });
});

var queueNamesToColors = {
    'Good' : 'rgba(0,255,255,0.4)',
    'Miss' : 'rgba(255,255,0,0.4)',
    'Unchecked' : 'White',
    'Clear' : 'Grey',
    'DNC' : 'Black',
};

var altNames = {
    Spec: 'special_price',
    Sale: 'salePrice',
};
$('th').each(function(){
    var nameElm = $(this).find('.name');
    var thName = nameElm.text(); 
    $.each(altNames, function(k,v) {
        if (thName == v) {
            nameElm.text(k);
        }
    });
    //thName = thName.toUpperCase();
    //nameElm.text(thName);
});

//do something based on current option. I don't think this is being used
$(function(){
    var option = $('#curOption').val();
    if (parseInt(option,10) == 3) {
        $('#blank-th').show();
        $('#blank-th').html('Notes');
    }
});

// filter events
$('.filter').on('change',function(){
    var select = $(this).find(':selected');
    var filter = $(this).attr('name');
    if (select.text() == 'View All') {
        $('td').each(function(){
            $(this).closest('tr').show();
        });
    } else if (select.text() == 'Hide Yellow') {
        $('td').each(function(){
            if ($(this).hasClass('text-warning') || $(this).text() == '') {
                $(this).closest('tr').hide();
            }
        });
    } else if (select.text() == 'Hide Red & Yellow') {
        $('td').each(function(){
            if ($(this).hasClass('text-warning') || $(this).hasClass('text-danger') || $(this).text() == '') {
                $(this).closest('tr').hide();
            }
        });
    } else if (select.text() == 'Show Only Coop+Deals') {
        $('td').each(function(){
            var str = $(this).text();
            var index = str.indexOf('Co-op Deals');
            if ($(this).hasClass('col-batchName') && str.indexOf('Co-op Deals') == -1) {
                $(this).closest('tr').hide();
            }
        });
    } else {
        $('td').each(function(){
            $(this).closest('tr').show();
        });
        $('td').each(function(){
            var tdvalue = $(this).text();
            if ($(this).hasClass('col-'+filter)) {
                if (tdvalue != select.text()) {
                    /*
                    alert(
                        'selected: '+select.text()+
                        ', fiter: '+filter+
                        ', tdvalue: '+tdvalue
                    );*/
                    $(this).closest('tr').hide();
                }
            } 
        });
    }
    stripeTable();
});


// recognize dates on page as current, past and ancient 
$('td').each(function(){
    var text = $(this).text(); 
    var col = $(this).attr('class');
    if (col == 'col-last_sold ') {
        var tdate = new Date(text);
        var year = tdate.getFullYear();
        var month = tdate.getMonth()+1;
        var day = tdate.getDate()+1;

        var check1 = new Date();
        check1.setMonth(check1.getMonth() - 2);
        var cy = check1.getFullYear();
        var cm = check1.getMonth()+1;
        var cd = check1.getDate()+1;
        
        var check2 = new Date();
        check2.setMonth(check2.getMonth() - 12);
        var cy = check2.getFullYear();
        var cm = check2.getMonth()+1;
        var cd = check2.getDate()+1;

        var date1 = year+'-'+month+'-'+day;
        var date2 = cy+'-'+cm+'-'+cd;

        year = parseInt(year,10);
        cy = parseInt(cy,10);
        if (tdate < check2) {
            $(this).addClass('text-danger');
        } else if (tdate < check1) {
            $(this).addClass('text-warning');
        }
    }
});


// remove duplicate rows inserted when finding items left out by mysql  
var missUpcs = [];
$('td').each(function() {
    if ( $(this).hasClass('col-upc') ) {
        html = $(this).html(); 
        if ( $.inArray(html, missUpcs) != -1 ) {
            $(this).closest('tr').hide();
        } else {
            missUpcs.push(html)
        }
    }
});


// clear all in queue
var clearAll = function(){
    c = confirm("Remove all items from this queue?");
    if (c === true) {
        var qv = $('#clearAll').val();
        var queueName = $('#clearAll').text();
        var id = $('clearAll').attr('id');
        var sessionName = $('#sessionName').val();
        var storeID = $('#storeID').val();
        $.ajax({
            type: 'post',
            url: 'SCS.php',
            data: 'queue='+queueName+'&qval='+qv+'&sessionName='+sessionName+'&storeID='+storeID+'&clearAll=1',
            dataType: 'json',
            success: function(json) {
                $('td').each(function(){
                    $(this).closest('tr').hide();
                });
                $('#textarea').val("");
                //$('#qcount').html("[0]");
                if (json.error) {
                    alert(json.error);
                }
            }
        });
    }
}

stripeTable();

var queue = $('#curQueue').val();
if (queue != 0 && queue != 1) {
    $('#bottom-content').prepend("<div><button style='width: 150px' id='clearAll' value='"+queue+"' class='btn btn-danger btn-sm' onclick='clearAll(); return false;'>ClearAll</button></div>");
}

var countRows = function()
{
    var n = 0;
    $('tr').each(function(){
        n++; 
    });

    return n;
}
var rows = countRows() - 1;
$('#extra-content').prepend('<div>Items in this queue: '+rows+'</div>');

$('.copy-text').focus(function(){
    $(this).select();
    var status = document.execCommand('copy');
    if (status == true) {
        $(this).parent().find('.status-popup').show()
            .delay(400).fadeOut(400);
    }
});

