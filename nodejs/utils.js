// common  utils

module.exports = {
	store: {},
	cs: function(msg,obj) {
		//if(!global.debug) return false;
		if(typeof obj!=='undefined'){
			console.log('========'+msg+'========');
			console.log(obj)
		} else {
			console.log(msg)
		}
	},		
	flog:function(msg) { //not used within this script.  referenced from global
		var pi=this;
	    if(typeof msg=='string') pi.cs( new Date() + ": "+ msg); 
	    else {
	        pi.cs( new Date() + ":<<<OBJECT");
	        pi.cs(msg);
	        pi.cs("END OBJECT>>>");
	    }
	},		
	/**
	* METHOD df
	*
	* Deep Object Fetcher/Tester
	*
	* This function safely checks to make sure that a namespace exists within an object and returns it
	* Use instead when attempting to access deeply spaced variables that may or may not exist.
	* Pass object and a string dot notated namespace
	*
	* Example:
	*
	*        app.doFunction(df(summaryData,'visitors.graphs.active',{}));
	*
	*   or with [] like this
	*
	*       df(obj,"myvar[1].k");
	*
	* @author Jeremy Dill
	* @param multi tobj - associatve array object to test
	* @param namespace - string with dot notation to test. 
	* @param default - default value to return if there is nothing at this location.
	* @return false, or the object value at this namespace if one exists
	*
	*/		
	df:function(tobj,namespace,def){
		if(typeof namespace==='undefined') return (typeof tobj !== 'undefined') ? tobj : (typeof def!=='undefined'?def:false);
	    namespace = namespace.split('.')
	    var cKey = namespace.shift();

	    function get(pObj, pKey) {
	        var bracketStart, bracketEnd, o;
            if (typeof(pObj) === 'undefined' || pObj===null || pObj===false ) {
	            return typeof def!=='undefined'?def:false;
	        }
	        bracketStart = pKey.indexOf("[");
	        if (bracketStart > -1) { //check for nested arrays
	            bracketEnd = pKey.indexOf("]");
	            var arrIndex = pKey.substr(bracketStart + 1, bracketEnd - bracketStart - 1);
	            pKey = pKey.substr(0, bracketStart);
	            var n = pObj[pKey];
	            o = n? n[arrIndex] : undefined;
	        } else {
	            o = pObj[pKey];
	        }
	        return o;
	    }

	    obj = get(tobj, cKey);
	    while (typeof obj!== 'undefined' && namespace.length) {
	        obj = get(obj, namespace.shift());
	    }
	    return (typeof obj !== 'undefined') ? obj : (typeof def!=='undefined'?def:false);
	},
	/**
	* METHOD ato
	* VERSION 1.3
	* setTimeout Hander: key based with auto-clear and early execution/cancel
	* @author Jeremy Dill
	* @param {string} key - a unique name for this timer.  call again with same key to manage/clear or update
	* @param {int|bool} timer - If a integer is passed, this is the timeout for setTimeout.  
								If bool, false will cancel execution of a previously set timer, true will execute function now and clear timeout.  
								If null or nothing is passed, function returns a handle to the timer and does nothing else.
	* @param {function} func - the function.  Has access to callee block vars as normal via closures
	* @returns {number|bool} - either returns the handle to the timeout or undefined if timer is a bool
	**/
	ato:function(key,timer,func){
		var self=this;
		if(!key&&func) key=func.toString();
		if(!key) return;
		var debug=0;
		key='_ato_'+key;
		if(typeof timer==='boolean'){
			if(typeof self.store[key+'_func']==='function') {
				if(timer) { // if true passed to timer, execute function now and clear timeout.
					if(debug) self.cs('execute now and clear timeout for '+key);
					clearTimeout(self.store[key]);
					self.store[key+'_func']();
				} else { // if false, just clear/cancel now.
					if(debug) self.cs('cancel timeout for '+key);
					clearTimeout(self.store[key]);
				}
			} else {
				if(debug) self.cs('WRN-timer function not found.  May have already expired for '+key);
			}
			delete self.store[key+'_func'];
			return; //return nothing
		}
		if(typeof timer==='number' && typeof func==='function') {
			if(self.store[key]) {
				clearTimeout(self.store[key]);
				delete self.store[key+'_func'];
				if(debug) self.cs('clearing timeout '+key);
			}
			self.store[key+'_func']=func;			
			self.store[key]=setTimeout(function(){func();delete self.store[key+'_func'];delete self.store[key];},timer);
			if(debug) self.cs('setting timeout for '+key+' to '+timer);
		} else {
			// just referencing
		}
		return self.store[key];
	}
}