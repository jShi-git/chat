//justin touch v1.1 https://github.com/yuminjustin/touch
! function(a) {
	var b, c, d;
	c = ["swipe", "swipeLeft", "swipeRight", "swipeUp", "swipeDown", "tap", "longTap", "drag"], c.forEach(function(c) {
		a.fn[c] = function() {
			var e = new d(a(this), c);
			return b = arguments[1] || {
				x: 100,
				y: 100
			}, e.start(), this.on(c, arguments[0])
		}
	}), d = function() {
		this.target = arguments[0], this.e = arguments[1]
	}, d.prototype.framework = function(a) {
		return a.changedTouches ? a.changedTouches[0] : a.originalEvent.touches[0]
	}, d.prototype.common = function() {
		return arguments[0].preventDefault(), {
			t: this.framework(arguments[0]),
			d: new Date
		}
	}, d.prototype.start = function() {
		var a = this;
		a.target.on("touchstart", function(b) {
			var c = a.common(b);
			a.ts = {
				x: c.t.pageX,
				y: c.t.pageY,
				d: c.d.getTime()
			}
		}), a.target.on("touchmove", function(b) {
			var c = a.common(b);
			return a.tm = {
				x: c.t.pageX,
				y: c.t.pageY,
				xs: a.ts.x,
				ys: a.ts.y
			}, "drag" == a.e ? void a.target.trigger(a.e, a.tm) : void 0
		}), a.target.on("touchend", function(b) {
			var c = a.common(b);
			a.tm = a.tm || a.ts, a.te = {
				x: a.tm.x - a.ts.x,
				y: a.tm.y - a.ts.y,
				d: c.d.getTime() - a.ts.d
			}, a.tm = void 0, a.factory()
		})
	}, d.prototype.factory = function() {
		var a = Math.abs(this.te.x),
			c = Math.abs(this.te.y),
			d = this.te.d,
			e = this.status;
		return 5 > a && 5 > c ? e = 300 > d ? "tap" : "longTap" : a < b.x && c > b.y ? e = 250 > d ? this.te.y > 0 ? "swipeDown" : "swipeUp" : "swipe" : c < b.y && a > b.x && (e = 250 > d ? this.te.x > 0 ? "swipeRight" : "swipeLeft" : "swipe"), e == this.e ? void this.target.trigger(this.e) : void 0
	}
}(window.jQuery || window.Zepto);

//=======================横屏判断===============================
function orient() {
	if (window.orientation == 0 || window.orientation == 180) {
		$('#restrict_heng').hide();
		return false;
	} else if (window.orientation == 90 || window.orientation == -90) {
		$('#restrict_heng').show();
		return false;
	}
}
$(window).bind('orientationchange', function(e) {
	orient();
});

/**
 * 弹框提示
 * @param  {[type]} data     [description]
 * @param  {[type]} showTime [description]
 * @return {[type]}          [description]
 */
function showAlert(data,showTime){
    if(showTime==undefined){
        showTime=3400;
    }
    if(data!='' && !$('#pop-notification').is(':visible')){
        $('#pop-notification').html(data).css('display', 'block');
        setTimeout(function(){
        	$('#pop-notification').css('display', 'none');
        }, showTime);
    }
}


//=======================调整内容框高度===============================
var barrage;

function setHeight() {
	var wHeight = $(window).height();
	var bHeight = $('#banner').height();
	var fHeight = 45;
	var cHeight = wHeight - bHeight - fHeight;
	$('#dialog').height(cHeight);
	barrage = $('#dialog').Barrage({
		className: ['line1', 'line2', 'line3', 'line3', 'line4', 'line5']
	});
}

$(function() {
	init();
	$("#bimg").load(function() {
		setHeight();
	});

	$('#send-trigger').tap(function() {
		onSubmit();
	});

	document.onkeydown = function(event) {
		var e = event || window.event;
		if (e && e.keyCode == 13) {
			$('#send-trigger').trigger('tap');
		}
	};
});