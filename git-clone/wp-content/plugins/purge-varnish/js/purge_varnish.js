function open_menu(e,s){var t,u,l;for(u=document.getElementsByClassName("tabcontent"),t=0;t<u.length;t++)u[t].style.display="none";for(l=document.getElementsByClassName("tablinks"),t=0;t<l.length;t++)l[t].className=l[t].className.replace(" active","");document.getElementById(s).style.display="block",e.currentTarget.className+=" active"}jQuery(document).ready(function(){jQuery("#post_expiration").length&&jQuery("#post_expiration").css("display","block"),jQuery(".ck_custom_url").change(function(){this.checked?(jQuery("div.custom_url").removeClass("hide_custom_url"),jQuery("div.custom_url").addClass("show_custom_url")):(jQuery("div.custom_url").removeClass("show_custom_url"),jQuery("div.custom_url").addClass("hide_custom_url"))})});