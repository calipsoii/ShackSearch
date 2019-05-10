window.updateWordCloudFilterDescr = function() {
    // 8. Retrieve the parent id from the data attributes.data("subthreadid")
    var descr = $("#word-filter option:selected").data("descr");
    $("#word-filter-description").text(descr);
}

window.updateWordCloudcolorsetDescr = function() {
    // 8. Retrieve the parent id from the data attributes.data("subthreadid")
    var descr = $("#colorset option:selected").data("descr");
    $("#word-colorset-description").text(descr);
}

window.saveCloudAsPNG = function() {
    /*
    html2canvas([document.getElementById('word-cloud-div')], {
        onrendered: function(canvas) {
            document.getElementById('canvas').appendChild(canvas);
            var data = canvas.toDataURL('image/png');
            var image = new Image();
            image.src = data;
            document.getElementById('image').appendChild(image);
        }
    });
    */

    if(!$(".img-fluid").length)
    {
        var q = document.getElementById("word-cloud-png-row");
        //var r = document.getElementById("word-cloud-canvas-row");
        var s = document.getElementById("word-cloud-info");
        s.style.display = "none";
        s.style.display = "flex";
        //if(s.style.display === "none") {
        //    s.style.display = "flex";
        //}
    
        html2canvas($("#word-cloud-div")[0], {
            width: $("#word-cloud-div").width(),
            height: $("#word-cloud-div").height()}).then(function(canvas) {
            //document.getElementById("word-cloud-canvas").appendChild(canvas);
            var data = canvas.toDataURL("image/png");
            var image = new Image();
            image.src = data;
            document.getElementById("word-cloud-png").appendChild(image).className = "img-fluid";
        });
        
        if(q.style.display === "none") {
            q.style.display = "block";
        }
    }
    else
    {
        var q = document.getElementById("word-cloud-png-row");
        if(q.style.display === "none") {
            q.style.display = "block";
        }
    }
    
    
    //if(r.style.display === "none") {
    //    r.style.display = "block";
    //}
    //if(s.style.display === "flex") {
    //    s.style.display = "none";
    //}
}

window.hideCloudPNGDiv = function() {
    var x = document.getElementById("word-cloud-png-row");
    if(x.style.display === "block") {
        x.style.display = "none";
    }
    var s = document.getElementById("word-cloud-info");
    s.style.display = "none";
}

window.toggleWordCloudQuickEdit = function() {
    var x = document.getElementById("quick-edit");
    if(x.style.display === "none") {
        x.style.display = "flex";
    } else {
        x.style.display = "none";
    }
}

/**
 *  When the user expands a very large post and then collapses it (either by
 *  directly clicking on the post or choosing another), the large post collapses
 *  but the readers window is still scrolled way down the page.
 * 
 * https://web-design-weekly.com/snippets/scroll-to-position-with-jquery/
 * https://stackoverflow.com/questions/9880472/determine-distance-from-the-top-of-a-div-to-top-of-window-with-javascript
 */
$.fn.scrollView = function () {
    return this.each(function () {
        $('html, body').animate({
            scrollTop: $(this).offset().top
        }, 100);
    });
}


/* 
    Accordion code to collapse/expand posts for reading. This entire
    block executes each time a page loads where this script is included.
    That means each page load will get all elements with class "accordion".
    An eventListener is registered on each element, with the code below the
    listener firing on each occurrence of the event.

    https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
    https://www.w3schools.com/howto/howto_js_accordion.asp

    The following workflow is fired:
    1) If any <svg> elements have the .svg-active class, reset to 0/0 and and remove class
    2) If any elements have the .highlight-parent class, remove it
    3) If any elements exist in sessionStorage, set margins back to stored value and remove from session
    4) If any elements have .collapsible-post-expanded class, change it to .collapsible-post-collapsed
        4a) For those elements, for firstChild add .d-none .d-md-flex
    5) For clicked element, push margin into sessionStorage and change margin
    6) For clicked element, remove .collapsible-post-collapsed and add .collapsible-post-expanded
    7) For clicked element firstChild, remove .d-none .d-md-flex
    8) For clicked element, retrieve parentId attribute
    9) For direct parentId, add .highlight-parent
    10) For direct parentId, calculate height from clicked element and draw SVG
    11) Enter for-loop, pulling parentId's up to 0
        11a) For each ancestor, draw SVG
    12) If the user collapsed a huge post and their selected post is now out of the viewport, move
        the viewpoint to show it

*/
var acc = document.getElementsByClassName("collapsible-post");
var i;

for (i = 0; i < acc.length; i++) {

    // onClick event registration (code only fires when user clicks)
    acc[i].addEventListener("click", function(event) {

        // Check if the user is clicking the same div they did last time to collapse it
        var sameDiv = false;
        if($(this).hasClass("collapsible-post-expanded")) {
            sameDiv = true;
        }

        // 1. Remove any previously drawn SVG lines
        $(".svg-active").each(function() {
            $(this).width(0);
            $(this).height(0);
            $(this).removeClass("svg-active");
        });

        // 2. Remove any previously highlighted parents & ancestors
        $(".highlight-parent").removeClass("highlight-parent");
        $(".highlight-ancestor").removeClass("highlight-ancestor");

        // 3. Check sessionStorage for any stored items and restore the saved margins
        if(sessionStorage.length > 0) {
            for (var i=0, len = sessionStorage.length; i  <  len; i++){
                var key = sessionStorage.key(i);
                if(key !== null) {
                    $('#'+key).css('margin-left',sessionStorage.getItem(key));
                    sessionStorage.removeItem(key);
                }
            }
        }

        // 4. Swap any expanded-post classes with collapsed-post
        $(".collapsible-post-expanded").each(function() { 
            $(this).removeClass("collapsible-post-expanded").addClass("collapsible-post-collapsed");
            // 4a. Hide author/date on small screens, display on larger ones
            $(this).children(":first").addClass("d-none d-md-flex");

            // By taking a measurement of the div we can force a "reflow"
            var offsetHeight = $(this).offsetHeight;
        });
        
        // If the user has clicked the same div to collapse it, don't do anything else, just
        // let the above code set the page back to default
        if(!sameDiv) 
        {
            // 5. Push the margin for selected item into sessionStorage and change margin
            sessionStorage.setItem(this.id,this.style.marginLeft);
            this.style.marginLeft = "1em";

            // 6. For this element, set the expanded CSS class
            $(this).removeClass("collapsible-post-collapsed").addClass("collapsible-post-expanded");

            // 7. Force display of the author and date
            $(this).children(":first").removeClass("d-none d-md-flex");

            // 8. Retrieve the parent id from the data attributes
            var parentId = this.dataset.parentid;

            // 9. Highlight the parent post (if it's not root post)
            if(parentId != threadId) {
                $('#'+parentId).addClass("highlight-parent");

                // 10. Draw the SVG line from the direct parent (if not immediately prior)

                // Ensure the parent isn't sitting right on top of the clicked element
                var thisTop = $(this).offset().top;
                //alert(thisTop);
                if($('#'+parentId).data("subthreadid") < (this.dataset.subthreadid - 1))
                {
                    var parentBot = $('#'+parentId).offset().top + $('#'+parentId).outerHeight(true);

                    // Grow the SVG drawing area between the two posts. Line is already drawn, just needs displayed
                    $('#'+parentId).children(".parent-svg").width(10);
                    $('#'+parentId).children(".parent-svg").height((thisTop - parentBot));

                    // Add the class so we can find it later
                    $('#'+parentId).children(".parent-svg").addClass("svg-active");
                }

                // 11. Draw lines from any previous ancestors as well
                var ancestorId = $('#'+parentId).attr("data-parentid");

                while(ancestorId != threadId) {
                    var ancestorBot = $('#'+ancestorId).offset().top + $('#'+ancestorId).outerHeight(true);

                    // 11a. Grow the SVG drawing area between the two posts. Line is already drawn, just needs displayed
                    $('#'+ancestorId).children(".parent-svg").width(10);
                    $('#'+ancestorId).children(".parent-svg").height((thisTop - ancestorBot));

                    // Add the class so we can find it later
                    $('#'+ancestorId).children(".parent-svg").addClass("svg-active");

                    // Add some border highlighting to the ancestor so the user can follow the chain with their eyes
                    $('#'+ancestorId).addClass("highlight-ancestor");

                    ancestorId = $('#'+ancestorId).attr("data-parentid");
                }

            }
        }

        // If the user just collapsed a huge post by clicking a bit further down, the collapse will
        // scroll everything in the DOM up and out of their view window. Move their window in these
        // cases so that they're not left disoriented further down the thread.
        var scrollTop     = $(window).scrollTop(),
            elementOffset = $(this).offset().top,
            distance      = (elementOffset - scrollTop);
        if(distance < -50) {
            $(this).scrollView();
        }

        /*
            If the user got here from the Search page, a Click() event will be fired, which will process all the above code
            to highlight the desired post and all that. But the page will load scrolled all the way to the top. And the highlighted
            post may be much further down the page. Instead of the post being ABOVE the viewport (as tested for above), it may
            be BELOW.
        */
       var scrollBottom = $(window).scrollTop() + $(window).height();
       var elementTop = $(this).offset().top;
       var distance = (scrollBottom - elementTop);
       if(distance < 0) {
            $(this).scrollView();
        }

    });

    // Code fires for every div every time the page loads. If user resizes the window the "click-for-more" icon
    // may disappear, so display it.
    var cdv = $(acc[i]);
    if(cdv.children(".collapsible-post-body")[0].scrollWidth <= cdv.innerWidth() &&
        cdv.children(".collapsible-post-body")[0].scrollHeight <= cdv.children(".collapsible-post-body")[0].clientHeight) {
        cdv.children(".collapsible-post-body").children(":first").children(".click-for-more").css('display','none');
    }
}

var acd = document.getElementsByClassName("thread-list-item");
var j;

for (j = 0; j < acd.length; j++) {

    // Code fires for every div every time the page loads. If user resizes the window the "click-for-more" icon
    // may disappear, so display it.
    var cda = $(acd[j]);
    if(cda.children(".thread-list-item-body")[0].scrollWidth <= cda.innerWidth() &&
        cda.children(".thread-list-item-body")[0].scrollHeight <= cda.children(".thread-list-item-body")[0].clientHeight) {
        cda.children(".thread-list-item-body").children(":first").children(".click-for-more").css('display','none');
    }

}