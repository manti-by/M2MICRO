/**
  * Default template js file
  * @package M2 Micro Framework
  * @subpackage Template
  * @author Alexander Chaika
  */

$(document).ready(function() {
    // hide message block after 5 seconds
    setTimeout(function() {
        $('#message').fadeOut(400);
    }, 5000);
    
    // hide div block by click
    $('.hide_this').click(function() {
        $(this).parent('div').fadeOut(400);
    });
    
    // move loader
    $('body').mousemove(function(e){
        $('#loader').css('left', e.pageX + 16).css('top', e.pageY - 16);
    });
});