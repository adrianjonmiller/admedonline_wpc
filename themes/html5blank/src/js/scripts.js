$(document).ready(function(){
	$(".flexslider").flexslider({
		animation: "slide"
	});
	$(window).on("load", function(){
		var images = $(document).find("img");
		$.each(images, function(){
			var that = $(this);
			var image = new Image();
			image.src = $(this).attr("src");
			image.onload = function() {
				that.css("max-width", this.width);
				that.css("display", "block");
			};
		});
	});
});