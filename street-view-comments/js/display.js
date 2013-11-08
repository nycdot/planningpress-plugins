(function(F){

  Backbone.emulateHTTP = true;

  F.LocationCollection = Backbone.Collection.extend({
    url: settings.backbone_url
  });

  F.FeedbackModel = Backbone.Model.extend({
    url: settings.feedback_url
  });


  jQuery(function(){
    // Disable caching for all ajax calls
    jQuery.ajaxSetup ({
      cache: false
    });

    var collection = new F.LocationCollection();

    // Init the views
    var mapSlider = new F.NavigatorView({
      el: '.dot-slider', collection: collection
    });

    var tooltip = new F.TooltipView({
      el: '.dot-tooltip-comments', collection: collection
    });

    var youarehere = new F.YouarehereTooltipView({
      el: '.dot-tooltip-youarehere', collection: collection
    });

    var feedbackActivity = new F.FeedbackActivityView({
      el: '.dot-feedback-activity', collection: collection
    });

    var feedbackList = new F.FeedbackListView({
      el: '.dot-feedback-container',
      colors: ['yellow', 'blue', 'magenta'],
      collection: collection
    });

    var streetview = new F.StreetviewView({
      el: '#dot-sv',
      collection: collection,
      panoOptions: {
        position: new google.maps.LatLng(0, 0),
        visible:true,
        addressControl: false,
        clickToGo: false,
        scrollwheel: false,
        linksControl: false,
        disableDoubleClickZoom: false,
        zoomControlOptions: {
          style: google.maps.ZoomControlStyle.SMALL
        }
      }
    });

    // console.log('got streetview', streetview);

    var feedbackForm = new F.FeedbackFormView({
      el: '.dot-survey-form',
      showFormEl: '#dot-add-feedback',
      collection: collection,
      maxChars: 200
    });

    var locationTitle = new F.LocationTitleView({
      el: '.dot-title',
      setTitle: function(title) {
        this.$el.html(settings.mainstreet + ' &amp; ' + title);
      }
    });

    // Fetch the location records
    collection.fetch({
      success: function(intersections, res) {

        // console.log(intersections);
        // Set the width of the container to match the chart width exactly
        var container = jQuery('#dot-container'),
            exactWidth = Math.floor(container.width() / intersections.size()) * intersections.size();
        container.width(exactWidth);
      }
    });
  });
  })(Fitzgerald);