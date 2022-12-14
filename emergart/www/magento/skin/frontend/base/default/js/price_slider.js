// Copyright (c) 2005 Marty Haught, Thomas Fuchs 
//
// See http://script.aculo.us for more info
// 
// Permission is hereby granted, free of charge, to any person obtaining
// a copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to
// permit persons to whom the Software is furnished to do so, subject to
// the following conditions:
// 
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
// LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
// OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
// WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

if(!Control) var Control = {};
Control.PriceSlider = Class.create();

// options:
//  axis: 'vertical', or 'horizontal' (default)
//
// callbacks:
//  onChange(value)
//  onSlide(value)
Control.PriceSlider.prototype = {
  initialize: function(handle, track, options) {
    var slider = this;
    
    if(handle instanceof Array) {
      this.handles = handle.collect( function(e) { return $(e) });
    } else {
      this.handles = [$(handle)];
    }
    
    this.track   = $(track);
    this.options = options || {};

    this.axis      = this.options.axis || 'horizontal';
    this.increment = this.options.increment || 1;
    this.step      = parseInt(this.options.step || '1');
    this.range     = this.options.range || $R(0,1);

    this.value     = 0; // assure backwards compat
    this.values    = this.handles.map( function() { return 0 });
    this.spans     = this.options.spans ? this.options.spans.map(function(s){ return $(s) }) : false;
    this.options.startSpan = $(this.options.startSpan || null);
    this.options.endSpan   = $(this.options.endSpan || null);

    this.restricted = this.options.restricted || false;

    this.maximum   = this.options.maximum || this.range.end;
    this.minimum   = this.options.minimum || this.range.start;

    // Will be used to align the handle onto the track, if necessary
    this.alignX = parseInt(this.options.alignX || '0');
    this.alignY = parseInt(this.options.alignY || '0');
    
    this.trackLength = this.maximumOffset() - this.minimumOffset();
    this.handleLength = this.isVertical() ? this.handles[0].offsetHeight : this.handles[0].offsetWidth;

    this.active   = false;
    this.dragging = false;
    this.disabled = false;

    if(this.options.disabled) this.setDisabled();

    // Allowed values array
    this.allowedValues = this.options.values ? this.options.values.sortBy(Prototype.K) : false;
    if(this.allowedValues) {
      this.minimum = this.allowedValues.min();
      this.maximum = this.allowedValues.max();
    }

    this.eventMouseDown = this.startDrag.bindAsEventListener(this);
    this.eventMouseUp   = this.endDrag.bindAsEventListener(this);
    this.eventMouseMove = this.update.bindAsEventListener(this);

    // Initialize handles in reverse (make sure first handle is active)
    this.handles.each( function(h,i) {
      i = slider.handles.length-1-i;
      slider.setValue(parseFloat(
        (slider.options.sliderValue instanceof Array ? 
          slider.options.sliderValue[i] : slider.options.sliderValue) || 
         slider.range.start), i);
      Element.makePositioned(h); // fix IE
      Event.observe(h, "mousedown", slider.eventMouseDown);
      Event.observe(h, "touchstart", slider.eventMouseDown);
    });
    
    Event.observe(this.track, "mousedown", this.eventMouseDown);
    Event.observe(document, "mouseup", this.eventMouseUp);
    Event.observe(document, "mousemove", this.eventMouseMove);
    Event.observe(this.track, "touchstart", this.eventMouseDown);
    Event.observe(document, "touchend", this.eventMouseUp);
    Event.observe(document, "touchmove", this.eventMouseMove);

    this.initialized = true;
  },
  dispose: function() {
    var slider = this;    
    Event.stopObserving(this.track, "mousedown", this.eventMouseDown);
    Event.stopObserving(document, "mouseup", this.eventMouseUp);
    Event.stopObserving(document, "mousemove", this.eventMouseMove);
    Event.stopObserving(this.track, "touchstart", this.eventMouseDown);
    Event.stopObserving(document, "touchend", this.eventMouseUp);
    Event.stopObserving(document, "touchmove", this.eventMouseMove);
    this.handles.each( function(h) {
      Event.stopObserving(h, "mousedown", slider.eventMouseDown);
      Event.stopObserving(h, "touchstart", slider.eventMouseDown);
    });
  },
  setDisabled: function(){
    this.disabled = true;
  },
  setEnabled: function(){
    this.disabled = false;
  },  
  getNearestValue: function(value){
    if(this.allowedValues){
      if(value >= this.allowedValues.max()) return(this.allowedValues.max());
      if(value <= this.allowedValues.min()) return(this.allowedValues.min());
      
      var offset = Math.abs(this.allowedValues[0] - value);
      var newValue = this.allowedValues[0];
      this.allowedValues.each( function(v) {
        var currentOffset = Math.abs(v - value);
        if(currentOffset <= offset){
          newValue = v;
          offset = currentOffset;
        } 
      });
      return newValue;
    }
    if(value > this.range.end) return this.range.end;
    if(value < this.range.start) return this.range.start;
    return value;
  },
  setValue: function(sliderValue, handleIdx){
    if(!this.active) {
      this.activeHandle    = this.handles[handleIdx];
      this.activeHandleIdx = handleIdx;
      this.updateStyles();
    }
    handleIdx = handleIdx || this.activeHandleIdx || 0;
    if(this.initialized && this.restricted) {
      if((handleIdx>0) && (sliderValue<this.values[handleIdx-1]))
        sliderValue = this.values[handleIdx-1];
      if((handleIdx < (this.handles.length-1)) && (sliderValue>this.values[handleIdx+1]))
        sliderValue = this.values[handleIdx+1];
    }
    sliderValue = this.getNearestValue(sliderValue);
    this.values[handleIdx] = sliderValue;
    this.value = this.values[0]; // assure backwards compat
    
    this.handles[handleIdx].style[this.isVertical() ? 'top' : 'left'] = 
      this.translateToPx(sliderValue, handleIdx);
    
    this.drawSpans();
    if(!this.dragging || !this.event) this.updateFinished();
  },
  setValueBy: function(delta, handleIdx) {
    this.setValue(this.values[handleIdx || this.activeHandleIdx || 0] + delta, 
      handleIdx || this.activeHandleIdx || 0);
  },
  translateToPx: function(value, handleIdx) {
    // Hack GR to allow two sliders show the same value without overlapping
    if (this.handles.length == 2) {
    	if (handleIdx != null) {
		      var offset = (value - this.range.start) / (this.range.end-this.range.start) * (this.trackLength - 2 * this.handleLength);
			  if (handleIdx > 0) {
			    return Math.round(offset + this.handleLength) + 'px';
			  }
			  else {
			    return Math.round(offset) + 'px';
			  }
    	}
    	else {
		      var offset = (value - this.range.start) / (this.range.end-this.range.start) * (this.trackLength - 2 * this.handleLength);
			    return Math.round(offset + this.handleLength) + 'px';
    	}
	}
    else {
      return Math.round(
      ((this.trackLength-this.handleLength)/(this.range.end-this.range.start)) * 
      (value - this.range.start)) + "px";
    }
  },
  translateToValue: function(offset) {
    // Hack GR to allow two sliders show the same value without overlapping
    var percent = 0;
    if (this.handles.length == 2) {
	  if (this.activeHandleIdx > 0) {
	  	percent = (offset - 1.5 * this.handleLength)/(this.trackLength - 2 * this.handleLength)
	  }
	  else {
    	percent = (offset + 0.5 * this.handleLength)/(this.trackLength - 2 * this.handleLength)
	  }
	  var value = percent * (this.range.end-this.range.start) + this.range.start; 
	  if (value < this.range.start) {
	    value = this.range.start;
	  }
	  else if (value > this.range.end) {
	    value = this.range.end;
	  }
	  return value;
	}
    else {
      return ((offset/(this.trackLength-this.handleLength) * 
        (this.range.end-this.range.start)) + this.range.start);
    }
  },
  getRange: function(range) {
    var v = this.values.sortBy(Prototype.K); 
    range = range || 0;
    return $R(v[range],v[range+1]);
  },
  minimumOffset: function(){
    return(this.isVertical() ? this.alignY : this.alignX);
  },
  maximumOffset: function(){
    return(this.isVertical() ?
      this.track.offsetHeight - this.alignY : this.track.offsetWidth - this.alignX);
  },  
  isVertical:  function(){
    return (this.axis == 'vertical');
  },
  drawSpans: function() {
    var slider = this;
    if(this.spans)
      $R(0, this.spans.length-1).each(function(r) { slider.setSpan(slider.spans[r], slider.getRange(r)) });
    if(this.options.startSpan)
      this.setSpan(this.options.startSpan,
        $R(0, this.values.length>1 ? this.getRange(0).min() : this.value ));
    if(this.options.endSpan)
      this.setSpan(this.options.endSpan, 
        $R(this.values.length>1 ? this.getRange(this.spans.length-1).max() : this.value, this.maximum));
  },
  setSpan: function(span, range) {
    if(this.isVertical()) {
      span.style.top = this.translateToPx(range.start, null);
      span.style.height = this.translateToPx(range.end - range.start + this.range.start, null);
    } else {
      span.style.left = this.translateToPx(range.start, null);
      span.style.width = this.translateToPx(range.end - range.start + this.range.start, null);
     }
  },
  updateStyles: function() {
    this.handles.each( function(h){ Element.removeClassName(h, 'selected') });
    Element.addClassName(this.activeHandle, 'selected');
  },
  startDrag: function(event) {
    if(Event.isLeftClick(event) || (Prototype.Browser.IE && document.documentMode >= 9 && event.buttons == 1) || event.touches) {
      if(!this.disabled){
        this.active = true;
        
        var handle = Event.element(event);
        //var pointer  = [Event.pointerX(event), Event.pointerY(event)];
        var pointer = (event.touches ? [event.touches[0].clientX, event.touches[0].clientY] : [Event.pointerX(event), Event.pointerY(event)]);
        if(handle==this.track) {
          var offsets  = Position.cumulativeOffset(this.track); 
          this.event = event;
          this.setValue(this.translateToValue( 
           (this.isVertical() ? pointer[1]-offsets[1] : pointer[0]-offsets[0])-(this.handleLength/2)
          ));
          var offsets  = Position.cumulativeOffset(this.activeHandle);
          this.offsetX = (pointer[0] - offsets[0]);
          this.offsetY = (pointer[1] - offsets[1]);
        } else {
          // find the handle (prevents issues with Safari)
          while((this.handles.indexOf(handle) == -1) && handle.parentNode) 
            handle = handle.parentNode;
        
          this.activeHandle    = handle;
          this.activeHandleIdx = this.handles.indexOf(this.activeHandle);
          this.updateStyles();
        
          var offsets  = Position.cumulativeOffset(this.activeHandle);
          this.offsetX = (pointer[0] - offsets[0]);
          this.offsetY = (pointer[1] - offsets[1]);
        }
      }
      Event.stop(event);
    }
  },
  update: function(event) {
   if(this.active) {
      if(!this.dragging) this.dragging = true;
      this.draw(event);
      // fix AppleWebKit rendering
      //if(navigator.appVersion.indexOf('AppleWebKit')>0) window.scrollBy(0,0);
      if (Prototype.Browser.WebKit && !event.touches) window.scrollBy(0, 0);
      Event.stop(event);
   }
  },
  draw: function(event) {
    //var pointer = [Event.pointerX(event), Event.pointerY(event)];
    var pointer = (event.touches ? [event.touches[0].clientX, event.touches[0].clientY] : [Event.pointerX(event), Event.pointerY(event)]);
    var offsets = Position.cumulativeOffset(this.track);
    pointer[0] -= this.offsetX + offsets[0];
    pointer[1] -= this.offsetY + offsets[1];
    this.event = event;
    this.setValue(this.translateToValue( this.isVertical() ? pointer[1] : pointer[0] ));
    if(this.initialized && this.options.onSlide)
      this.options.onSlide(this.values.length>1 ? this.values : this.value, this);
  },
  endDrag: function(event) {
    if(this.active && this.dragging) {
      this.finishDrag(event, true);
      Event.stop(event);
    }
    this.active = false;
    this.dragging = false;
  },  
  finishDrag: function(event, success) {
    this.active = false;
    this.dragging = false;
    this.updateFinished();
  },
  updateFinished: function() {
    if(this.initialized && this.options.onChange) 
      this.options.onChange(this.values.length>1 ? this.values : this.value, this);
    this.event = null;
  },
  resize: function() {
    //console.log('trackLength: ' + this.trackLength);
    var slider = this;
    var onChange = this.options.onChange;
    this.options.onChange = null;
    this.trackLength = this.maximumOffset() - this.minimumOffset();
    this.handleLength = this.isVertical() ? this.handles[0].offsetHeight : this.handles[0].offsetWidth;
    this.drawSpans();
    this.handles.each( function(h,i) {
      i = slider.handles.length-1-i;
      slider.setValue(parseFloat(
        (slider.options.sliderValue instanceof Array ?
          slider.options.sliderValue[i] : slider.options.sliderValue) ||
         slider.range.start), i);
      Element.makePositioned(h); // fix IE
      Event.observe(h, "mousedown", slider.eventMouseDown);
      Event.observe(h, "touchstart", slider.eventMouseDown);
    });

    //console.log(this.spans[0].id + ": " + this.spans[0].style.width);
    this.options.onChange = onChange;
  },
  needsResize: function () {
    var trackLength = this.trackLength;
    this.trackLength = this.maximumOffset() - this.minimumOffset();
    var range = this.getRange(0);
    var span = this.spans[0];
    var expectedWidth = this.translateToPx(range.end - range.start + this.range.start, null);
    this.trackLength = trackLength;
    return span.style.width != expectedWidth;
  }
}