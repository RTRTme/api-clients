THIS FOLDER HOUSES A COLLECTION NODE JS SCRIPTS
THIS SOFTWARE COMES WITH NO WARRANTIES, USE AT YOUR OWN RISK.
============

REQUIRES
============
node.js v10.17+


USAGE - DEPENDS ON SCRIPT
============



DOCUMENTATION
=============
The code is untested...but, generally, rts-interface is a module that can be used as follows:
var net = require('net');
var pi = require('./utils.js');
var rts_interface = require('./rts-interface.js');
var rts=new rts_interface();

var record=	{
		event_name: 'TESTEVENT',//<event name or special>
		loc: 'START', //alias--one at a time please,
		conn_id: 20202, // <hash for connid, if does not match for existing connection,  we will disconnect and reconnect>
		//--if tag is not provided, we will just establish the connection without sending any times--//
		tag: 101, //<tag>
		time: '10:20:10.232' <time of day - see protocol https://rtrt.me/tcp-timing-data-protocol  >
		seqnr: 1, //<seqence number - will use a derivative of the ts>
		quality: 10, //<optional: quality of read as int>,
		rts_host: <rts host name>
	};

//establish connection and send a record.
rts.streamRecord(record);

LICENSE
============
Copyright (c) 2020, Dilltree Inc    https://rtrt.me
All rights reserved.

The BSD license applies to the code in the this file
You can use or modify this code in your software, commercial or otherwise.

Redistribution and use in source, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER
OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


CONTACT
============
Jeremy Dill - jeremy@dilltree.com - http://rtrt.me/contact