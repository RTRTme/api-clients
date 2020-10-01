var pi = require('./utils.js');
var net = require('net');
var flog = pi.flog;
let rtsInterface = function() {
  	var self=this;
    self.rts_pool={}; //pool of connections keyed by location.  We only need 1 connection per location.
    self.noiselevel=1; // higher for more noise (debug logging)
    self.retry_attempts=100; 
  	self.current_retry=0; 
	self.handleRtsDrop = function(obj, retry, cb){ 
        
		var poolkey=obj['event_name']+obj['loc'];
        if(self.noiselevel>1) flog("STREAM handleRtsDrop "+obj['event_name']+" : "+obj['loc']+" poolkey:"+poolkey+" socket?"+pi.df(self,'rts_pool.'+poolkey+'.socket') );
    	if(pi.df(self,'rts_pool.'+poolkey+'.socket')) self.rts_pool[poolkey]['socket'].write('goodbye\r\n'); //writing goodbye will effectively kill conn
    	//self.rts_pool[poolkey]['socket'] = null;
    	if(retry && self.current_retry<=self.retry_attempts){
            self.current_retry++;
    		pi.ato('reconnect_'+poolkey,2000,function(){
    			if(self.noiselevel>0) flog("STREAM "+obj['conn_id']+": attempting to reconnect to rts..."+self.current_retry);
    			self.connectToRts(obj, cb, true);
    		});
    	} else {
            if(cb) return cb({status:'error'});
        }
    }
    //cb returns connnection in first param. true for 2nd param if RRTB
    self.connectToRts = function(obj, cb, reconnect){
    	var poolkey=obj['event_name']+obj['loc'];
        var host=obj.rts_host;
    	var rtscli = net.connect({port: 3490,host:host}, function() { //'connect' listener
    	 	flog('**'+poolkey+' RTS CONNECTED, authenticating');
            self.current_retry=0;
    		self.rts_pool[poolkey] = {status:'authenticating', socket:rtscli, conn_id:obj['conn_id']};
    	  	rtscli.write('auth~nodeInteface~'+global.creds.ADMIN_APP_ID+'~'+global.creds.ADMIN_TOKEN+'\r\n');
    	});
    	rtscli.on('error', function(e){
    		if (e.code == 'ECONNREFUSED'){
    			flog("Connection to RTS refused! (RTS most likely down)");
    			self.handleRtsDrop(obj, 1, cb);
    		}
    	});
    	rtscli.on('data', function(data){
    		var recvline = data.toString().split('\r\n');
    		for (var i =0; i < recvline.length-1; i++){
    			if(self.noiselevel>1) flog("**"+obj['loc']+" RTS SAID: "+recvline[i]);
    			var out=JSON.parse(recvline[i]);
    			// handle auth ack
    			if(pi.df(out,'ack.cmd')=='auth' && pi.df(out,'ack.resp.success')){
    				//do init
                    if(obj.do_not_init) {
                        //do nothing, just callback
                        self.rts_pool[poolkey].status='connected';
                        if(cb) cb(self.rts_pool[poolkey]);
                    } else {
                        rtscli.write('init~'+obj.event_name+'~'+obj.loc+'~'+obj.conn_id+'\r\n');
                    }
    			}

    			// handle init ack
    			if(pi.df(out,'ack.cmd')=='init'){
    				if(pi.df(out,'ack.resp.success')){
    					self.rts_pool[poolkey].status='initializing';
    					//do init
    					flog('init was successful for '+obj.event_name+'~'+obj.loc+'~'+obj.conn_id);
    				} else {
    					var errtype=pi.df(out,'ack.resp.error.type');
    					switch(errtype){
    						case 'no_results':
    						case 'event_not_valid':
    							self.rts_pool[poolkey]['status']='invalid';
                                self.rts_pool[poolkey]['socket']=null; //rts will force disconnect
    						break;
    					}
    					flog('ERROR on init:'+errtype);
    					//we won't get any further here, callback now.
    					if(cb) cb(self.rts_pool[poolkey]);
    				}
    			}

    			// handle start 
    			if(pi.df(out,'cmd')=='start'){
    				self.rts_pool[poolkey].status='connected';

                    // not useing RTS resume, but store as reference
    				self.rts_pool[poolkey].resume_seqnr=pi.pint(pi.df(out,'lastseq'));

    				if(poolkey.indexOf('RRTB|')===0){
                        flog('starting RRTB from  '+self.rts_pool[poolkey].resume_seqnr);
                        if(cb) cb(self.rts_pool[poolkey],true,reconnect);
    				} else {
    					flog('starting normal from  '+self.rts_pool[poolkey].resume_seqnr);
    					if(cb) cb(self.rts_pool[poolkey],true,reconnect);
    				}
    				rtscli.write('ack~start\r\n');
    			}		
                
                if(typeof self.rts_pool[poolkey].handleCommand=='function') self.rts_pool[poolkey].handleCommand(out);

    			// handle goodbye 
    			if(pi.df(out,'ack.cmd')=='goodbye'){
    				self.rts_pool[poolkey].socket.destroy();
					delete self.rts_pool[poolkey];
    			}


    		}
    	});
    	rtscli.on('end', function() {
            if (self.rts_pool[poolkey] && self.rts_pool[poolkey]['status']!='invalid'){
            	self.handleRtsDrop(obj, 1, cb);
            }
    	});	
    };

	/**
	* METHOD streamRecord
	* connect and/or send time to RTS
	*
	* This connects and maintains connection to RTS, sending in times 
	* Pass object with following params (REFER TO https://rtrt.me/tcp-timing-data-protocol)
	*
	{
		event_name: '<event name or special>',
		loc: 'point location', //one at a time please,
		conn_id: <hash for connid, if does not match for existing connection,  we will disconnect and reconnect>
		//--if tag is not provided, we will just establish the connection without sending any times--//
		tag: <tag>
		time: <time of day - see protocol>
		seqnr: <seqence number - will use a derivative of the ts>
		quality: <optional: quality of read as int>
		meta: <optional: any meta data for read - see protocol>
        rts_host: <rts host name>
	}
	*
	*
	*/
    self.streamRecord = function(obj,cb){

        //make up a unique poolkey
    	var poolkey=obj.event_name+obj.loc;
 

    	// drop now if connection id changed
        //THIS HAS NEVER BEEN TESTED?
    	if(self.rts_pool[poolkey] && self.rts_pool[poolkey].conn_id!=obj.conn_id){
    		pi.ato('timeout_'+poolkey,true); //execute and cancel
    	}

    	//this is a read
    	if(obj.tag){
	    	var writeread=function(){
	    		if(!self.rts_pool[poolkey]||!self.rts_pool[poolkey].socket) {
	    			flog('ERR-expecting socket here!');
	    			setTimeout(function(){ self.streamRecord(obj,cb)},1000);
	    			return;
	    		}

                //if(obj.seqnr>self.rts_pool[poolkey].resume_seqnr){ //dont use RTS resume, handling internally
				var read='read~'+obj.tag+'~'+obj.loc+'~'+obj.time+'~'+obj.seqnr;
				if(obj.quality) read+='~'+obj.quality;
				if(obj.meta) read+='~'+obj.meta;
	    		self.rts_pool[poolkey].socket.write(read+'\r\n');
		    	
	    		return cb();
	    	}
			if (! self.rts_pool[poolkey] ){
				self.rts_pool[poolkey] = {status:'connecting',conn_id:obj.conn_id};
				self.connectToRts(obj, writeread);
			} else {
				if(self.rts_pool[poolkey].status!='connected') {
					if(self.rts_pool[poolkey].status=='invalid'){
						flog('event/point not matched--just finish processing without writing a time');	
						return cb();
					}
					//requeue
					flog('wait a minute, requeuing '+obj.tag+' while connecting');
					setTimeout(function(){ self.streamRecord(obj,cb)},1000);
					return;
				} else {
					if(self.rts_pool[poolkey].status=='connected'){
						// all was ready, write.
						return writeread();
					}
				}
			}	    	
	    } else {
	    	// this is just to init connection
			if (! self.rts_pool[poolkey] || self.rts_pool[poolkey]['status']=='invalid'){
                if(self.noiselevel>1) flog('CONNECTION '+poolkey+" STARTUP");
				self.rts_pool[poolkey] = {status:'connecting',conn_id:obj.conn_id};
				self.connectToRts(obj,cb);
			} else {
            // this is essentially a 'ping' by the box with no reads in it
                //flog('already established');
            
                if(self.noiselevel>1) flog('CONNECTION '+poolkey+" ping...");
				return cb();
				//connection was already established.
			}
	    }
    }
};

module.exports = rtsInterface;
