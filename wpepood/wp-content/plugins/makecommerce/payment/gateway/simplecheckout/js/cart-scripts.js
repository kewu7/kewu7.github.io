jQuery(document).on("updated_cart_totals", function(){
    var rand = Math.round(Math.random() * 100000);
    window.history.pushState({rand: rand}, "Rand "+rand, "./?r="+rand);
});