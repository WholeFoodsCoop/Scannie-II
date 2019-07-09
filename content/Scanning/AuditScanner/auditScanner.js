$(document).ready(function(){
    enableLinea('#upc', function(){
        $('#my-form')
            .append('<input type=hidden name=linea value=1 />')
            .submit();
    });
});

function queue(store_id)
{
    var upcB = document.getElementById("upc").value;
    $.ajax({
        type: 'post',
        url: 'AuditUpdate.php',
        data: 'upc='+upcB+'&store_id='+store_id,
        error: function(xhr, status, error)
        {
            alert('error:' + status + ':' + error + ':' + xhr.responseText)
        },
        success: function(response)
        {
            $('#ajax-resp').html(response);
        }
    })
    .done(function(data){

    })
}

function queueNote()
{
    var username = $('#username').val();
    var upc = $('#upc').val();
    var note = $('#note').val();
    $.ajax({
        type: 'post', 
        data: 'upc='+upc+'&note='+note+'&username='+username,
        success: function(response) {
            $('#counter').trigger('click');
        }
    });
}

$( "button" ).click(function() {
  var text = $( this ).text();
  $( "note" ).val( text );
});

function qm(msg)
{
    document.getElementById("note").value = msg;
}

$(document).ready(function(){
    var upc = $('#upc').val() + '';
    $( "#keyCL" ).click(function() {
        $('#upc').val('0');
        upc = 0;
        updateModalText();
    });
    $( "#key1" ).click(function() {
        $('#upc').val(upc+'1');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key2" ).click(function() {
        $('#upc').val(upc+'2');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key3" ).click(function() {
        $('#upc').val(upc+'3');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key4" ).click(function() {
        $('#upc').val(upc+'4');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key5" ).click(function() {
        $('#upc').val(upc+'5');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key6" ).click(function() {
        $('#upc').val(upc+'6');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key7" ).click(function() {
        $('#upc').val(upc+'7');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key8" ).click(function() {
        $('#upc').val(upc+'8');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key9" ).click(function() {
        $('#upc').val(upc+'9');
        upc = $('#upc').val();
        updateModalText();
    });
    $( "#key0" ).click(function() {
        $('#upc').val(upc+'0');
        upc = $('#upc').val();
        updateModalText();
    });
});

function get_auto_par()
{
    var par = $('#auto_par_value').val();
    par = parseFloat(par);
    par = par.toPrecision(3);
    $('#auto_par').text('PAR: ');
    $('#par_val').text(par);
}
$(document).ready( function() {
   get_auto_par();
   updateModalOnload();
});

function formSubmitter()
{
    //$('#my-form').submit();
    $('#counter').trigger('click');
}

function updateModalOnload()
{
    $('#btn-modal').click( function () {
        updateModalText();
    });
}

function updateModalText()
{
    $text = $('#upc').val();
    $('#modal-text').text($text);
}

function startLoading()
{
    $('#progressBar').show();
}
function endLoading()
{
    $('#progressBar').hide();
}

$('#counter').click(function(){
    document.forms['main_form'].submit();
});

$(function(){
    var isOnSale = $('#isOnSale').val();
    if ($('#isOnSale').val() == 'true/') {
        $('body').css('background','green');
        $('body').css('background','linear-gradient(135deg, #44f441, #0e280a)');
        $('body').css('color','white');
    }
});

$('#btn-action').click(function(){
    $('#menu-action').show();
});

$('#exit-action-menu').click(function(){
    $('#menu-action').hide();
});

$('#mod-narrow').click(function(){
    var upc = $('#upc').val();
    $.ajax({
        type: 'post',
        url: 'AuditScanner.php',
        data: 'upc='+upc+'&action=mod-narrow',
        success: function(resp)
        {
            alert('Success!');
            $('#counter').trigger('click');
        },
        error: function(resp)
        {
            alert('Action Failed');
        }
    });
});

$('#mod-in-use').click(function(){
    var upc = $('#upc').val();
    $.ajax({
        type: 'post',
        url: 'AuditScanner.php',
        data: 'upc='+upc+'&action=mod-in-use',
        success: function(resp)
        {
            alert("Success\n"+resp);
            //$('#menu-action').hide();
            $('#counter').trigger('click');
        },
        error: function(resp)
        {
            alert('Action Failed');
        }
    });
});

$('.edit-btn').click(function(){
    var table = $(this).attr('data-table');
    var column = $(this).attr('data-column');
    var c = confirm('Edit '+column+'?');
    var upc = $('#upc').val();
    var brand1 = $('#brand1_v').text();
    var description1 = $('#description1_v').text();
    var size = $('#size_v').text();
    var brand2 = $('#brand2_v').text();
    var description2 = $('#description2_v').text();
    if (c == true) {
        var newtext = prompt('Enter new '+column, eval(column));
        newtext = encodeURIComponent(newtext);
        if (newtext != null) {
            $.ajax({
                type: 'post',
                url: 'AuditScanner.php',
                data: 'upc='+upc+'&action=mod-edit&newtext='+newtext+'&table='+table+'&column='+column,
                success: function(resp)
                {
                },
                error: function(resp)
                {
                }
            });
        }
        $('#counter').trigger('click');
    }
});

$('.update-section').change(function(){
    var mapID = $(this).attr('data-mapID');
    var upc = $('#upc').val();
    var floor_section = $(this).val();
    $.ajax({
        type: 'post',
        data: 'mapID='+mapID+'&floor_section='+floor_section+'&upc='+upc,
        success: function(response)
        {
            $('#floor-section-edit').hide();
            alert('success');
        }
    });
});

$('.scanicon-trash').click(function(){
    var mapID = $(this).attr('data-mapID');
    $.ajax({
        type: 'post',
        data: 'mapID='+mapID+'&delete_mapID=true',
        success: function(response)
        {
            $('#floor-section-edit').hide();
            alert('success');
        }
    });
});

$('#upc').keydown(function(e){
    if (e.keyCode == 13) {
        e.preventDefault();
        $('#counter').trigger('click');
    }
});

$('#note').keydown(function(e){
    if (e.keyCode == 13) {
        e.preventDefault();
        queueNote();
        //$('#counter').trigger('click');
    }
});
$('#submit-note').click(function(){
    queueNote();
});
