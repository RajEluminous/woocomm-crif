
/* window.onload = function(event) {
	alert("got it");
} */	
jQuery(document).ready(function($){
	
	// Get the modal
	var modal = document.getElementById("myModal");

	
	// Get the <span> element that closes the modal
	var span = document.getElementsByClassName("close")[0];

	/*
	// Get the button that opens the modal
	var btn = document.getElementById("myBtn");

	// When the user clicks the button, open the modal 
	btn.onclick = function() {
	  modal.style.display = "block";
	}

	// When the user clicks on <span> (x), close the modal
	span.onclick = function() {
	  modal.style.display = "none";
	}
	*/
	// When the user clicks anywhere outside of the modal, close it
	window.onclick = function(event) {
	  if (event.target == modal) {
		modal.style.display = "none";
	  }
	}	
	
	// To check if bacs is checked
	jQuery( 'body' ).on( 'updated_checkout', function(e) {
        usingGateway(e);
        jQuery('input[name="payment_method"]').change(function(e){             
              usingGateway(e);
        });
    });
	
});
function usingGateway(e){
    console.log(jQuery("input[name='payment_method']:checked").val());
	var modal = document.getElementById("myModal");
	var crif_userid = jQuery('#crif_userid').val();
	var cokid = 'crif_usr_'+crif_userid;
	
	var ccval = readCookie(cokid);
	console.log(ccval);
    if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'bacs'){
				
		if(ccval) {		 	
          jQuery("input[name='payment_method']:checked").prop( "checked", false ); 
		  jQuery(".payment_box.payment_method_bacs").hide();		
		  modal.style.display = "block";
		  jQuery("#myModal").clone().show();
		}
    } 
}   
function readCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        } 
