ipico-rtrt.php: 
==============================================================================

DESCRIPTION
===========
Tail and Stream iPico data file to RTRT.me data via the TCP protocol.


REQUIRES
============
php 5.26+
php cli (via command line)

USAGE
============
Change CONFIGURATION section as needed then run as php cli script.

    usage: php ipico-rtrt.php [event name] [location name] [ipico file path] [rewind (1 to prevent resume)]


DOCUMENTATION
=============
General documentation for RTRT.me is available at:

    http://rtrt.me/docs

	
	Instructions for running PHP script in Windows

		First, you'll need to get PHP.  There are a couple installer projects out as seen in this video http://www.youtube.com/watch?v=gVYprZJ17k4, but those are kind of overkill since all you need is php cmd line client.

		I just did this:

		1) Download http://windows.php.net/downloads/releases/php-5.4.22-Win32-VC9-x86.zip
		2) Extracted into c:\PHP
		3) Then, I put the ipico-rtrt.php script c:\RTRT 
		4) From CMD prompt, you should be able to run the script like this.

		cd c:\RTRT

		c:\RTRT> c:\PHP\php.exe c:\RTRT\ipico-rtrt.php TEST5K FINISH c:\IPICO_DATA_FILE.txt

		usage: ipico-rtrt.php [event name] [location name] [ipico file path] [rewind (1 to prevent resume)]


		Stream should connect, and data should transmit.   Anytime a new row is appended to the file, it should send immediately.

		NOTE: You are prompted to press enter after you run the script.  If you want to redirect output to a log file with >,  you can disable the prompt by editing the php file and setting '$silent=true' on line 16.


LICENSE
============
Copyright (c) 2013, Dilltree Inc    http://dilltree.com
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