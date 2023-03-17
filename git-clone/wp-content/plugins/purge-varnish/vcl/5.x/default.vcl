# Combined VCL for all hosts

# Marker to tell the VCL compiler that this VCL has been adapted to the
# new 4.0 format.
vcl 4.0;

# Backend definition
backend default {
	.host = "127.0.0.1";
	.port = "8881";
	.connect_timeout = 600s;
	.first_byte_timeout = 600s;
	.between_bytes_timeout = 600s;
	.max_connections = 800;
}

# Uncomment to enable the H2 backend
backend h2 {
	.host = "127.0.0.1";
	.port = "8082";
	.connect_timeout = 600s;
	.first_byte_timeout = 600s;
	.between_bytes_timeout = 600s;
	.max_connections = 800;
}

# Import Varnish Standard Module so I can serve custom error pages
import std;

acl purge {
	"173.11.187.41";
	"50.28.11.223";
	"localhost";
}

sub vcl_recv {

	if (req.restarts == 0) {
		if (req.http.x-forwarded-for) {
			set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
		} else {
			set req.http.X-Forwarded-For = client.ip;
		}
	}

	if (req.method == "POST") {
		return (pass);
	}

	if (req.http.upgrade ~ "(?i)websocket") {
		return (pipe);
	}

	# Send HTTP/2 requests to the proper backend
	if (req.http.protocol ~ "HTTP/2") {
		set req.backend_hint = h2;
	}
	else {
		set req.backend_hint = default;
	}

	# Ignore all traffic to analytics2.bigdinosaur.org
	if (req.http.host ~"analytics2.bigdinosaur.org") {
		return (pass);
	}

	# Ignore all traffic to analytics.bigdinosaur.net
	if (req.http.host ~"analytics.bigdinosaur.net") {
		return (pass);
	}

	# Ignore all non-image traffic sent to bigsaur.us
	if (req.http.host ~"bigsaur.us") {
		if (!(req.url ~ "^/img/")) {
			return (pass);
		}
	}

	# Cache only the static assets in Discourse's "assets" dir and pass everything else
	if (req.http.host ~"discourse.bigdinosaur.org") {
		if (!(req.url ~ "(^/uploads/|^/assets/|^/user_avatar/)" )) {							  
			return (pass);
		}
	}

	# Ignore traffic to Ghost blog admin stuff
	if (req.http.host ~"blog.bigdinosaur.org") {
		if (req.url ~ "^/(api|signout)") {
			return (pass);
		}
		elseif (req.url ~ "^/ghost" && (req.url !~ "^/ghost/(img|css|fonts)")) {
			return (pass);
		}
	}

	# Same for LittleL blog
	if (req.http.host ~"littlel.bigdinosaur.org") {
		if (req.url ~ "^/(api|signout)") {
			return (pass);
		}
		elseif (req.url ~ "^/ghost" && (req.url !~ "^/ghost/(img|css|fonts)")) {
			return (pass);
		}
	}

	# Same for Ghost 1.0 Staging
	if (req.http.host ~"ghostbetax.bigdinosaur.org") {
		if (req.url ~ "^/(api|signout)") {
			return (pass);
	}
		elseif (req.url ~ "^/ghost" && (req.url !~ "^/ghost/(img|css|fonts)")) {
			return (pass);
		}
	}	

	# Remove cookies from things that should be static, if any are set
	if (req.url ~ "\.(png|gif|jpg|swf|css|js|ico|css|js|woff|ttf|eot|svg)(\?.*|)$") {
		unset req.http.Cookie;
		return (hash);
	}
	if (req.url ~ "^/images") {
		unset req.http.cookie;
		return (hash);
	}

	# Added by me to see if I can force Varnish to use X-Forwarded-For
	unset req.http.X-Forwarded-For;
	set req.http.X-Forwarded-For = client.ip;

	# allow PURGE from localhost, server's public IP, and my static IP
	if (req.method == "PURGE") {
		if (std.ip(req.http.X-forwarded-for, "0.0.0.0") !~ purge) {
			return (synth(405,"No purge 4 U."));
		}
		if (req.http.X-Purge-Method == "regex") {
			ban("req.url ~ " + req.url + " &amp;&amp; req.http.host ~ " + req.http.host);
			return (synth(200, "Purge block regex ban added."));
		}
	return (purge);
	}

	if (req.method == "BAN") {
		if (std.ip(req.http.X-forwarded-for, "0.0.0.0") !~ purge) {
			return (synth(405,"No ban 4 U."));
		}
		if (req.http.X-Purge-Method == "regex") {
			ban("req.url ~ " + req.url + " &amp;&amp; req.http.host ~ " + req.http.host);
			return (synth(200, "Ban block regex ban added."));
		}
		ban("req.http.host == " + req.http.host + " && req.url == " + req.url);
		return(synth(200, "Ban block ban added"));
	}

	# Remove Google Analytics and Piwik cookies so pages can be cached
	if (req.http.Cookie) {
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(__[a-z]+|has_js)=[^;]*", "");
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_pk_(ses|id)[\.a-z0-9]*)=[^;]*", "");
	}
	if (req.http.Cookie == "") {
		unset req.http.Cookie;
	}

	# Wordpress stuff
	if (req.http.host ~"spacecityweather.com") {
		if (req.url ~ "/feed(/)?") {
			return ( pass );
		}
		if (req.url ~ "wp-cron\.php.*") {
			return ( pass );
		}
		if (req.method != "GET" && req.method != "HEAD") {
			return ( pass );
		}

		if (req.url ~ "\.(gif|jpg|jpeg|swf|ttf|css|js|flv|mp3|mp4|pdf|ico|png)(\?.*|)$") {
			unset req.http.cookie;
			set req.url = regsub(req.url, "\?.*$", "");
	  	}
	  	if (req.url ~ "\?(utm_(campaign|medium|source|term)|adParams|client|cx|eid|fbid|feed|ref(id|src)?|v(er|iew))=") {
			set req.url = regsub(req.url, "\?.*$", "");
	  	}
	  	if (req.url ~ "wp-(login|admin)" || req.url ~ "preview=true" || req.url ~ "xmlrpc.php") {
			return (pass);
	  	}
	 	if (req.http.cookie) {
			if (req.http.cookie ~ "(wordpress_|wp-settings-)") {
				return(pass);
			} else {
				unset req.http.cookie;
			}
		}
	}

	if (req.http.host ~"scwstaging.bigdinosaur.org") {
		if (req.url ~ "/feed(/)?") {
			return ( pass );
		}
		if (req.url ~ "wp-cron\.php.*") {
			return ( pass );
		}
		if (req.method != "GET" && req.method != "HEAD") {
			return ( pass );
		}

		if (req.url ~ "\.(gif|jpg|jpeg|swf|ttf|css|js|flv|mp3|mp4|pdf|ico|png)(\?.*|)$") {
			unset req.http.cookie;
			set req.url = regsub(req.url, "\?.*$", "");
	  	}
	  	if (req.url ~ "\?(utm_(campaign|medium|source|term)|adParams|client|cx|eid|fbid|feed|ref(id|src)?|v(er|iew))=") {
			set req.url = regsub(req.url, "\?.*$", "");
	  	}
	  	if (req.url ~ "wp-(login|admin)" || req.url ~ "preview=true" || req.url ~ "xmlrpc.php") {
			return (pass);
	  	}
	 	if (req.http.cookie) {
			if (req.http.cookie ~ "(wordpress_|wp-settings-)") {
				return(pass);
			} else {
				unset req.http.cookie;
			}
		}
	}

	return (hash);
}

sub vcl_pass {
	set req.http.connection = "close";
	# Fix broken behavior showing tons of requests from 127.0.0.1 with Discourse
	if (req.http.X-Forwarded-For) {
		set req.http.X-Forwarded-For = req.http.X-Forwarded-For;
	} else {
		set req.http.X-Forwarded-For = regsub(client.ip, ":.*", "");
	}
}

sub vcl_pipe {
	if (req.http.upgrade) {
		set bereq.http.upgrade = req.http.upgrade;
	}
}

sub vcl_backend_response {
	set beresp.http.x-url = bereq.url;
	set beresp.http.X-Host = bereq.http.host;

	# Strip cookies before static items are inserted into cache.
	if (bereq.url ~ "\.(png|gif|jpg|swf|css|js|ico|html|htm|woff|eof|ttf|svg)$") {
		unset beresp.http.set-cookie;
	}
	if (bereq.http.host ~ "www.chroniclesofgeorge.com") {
		set beresp.ttl = 1008h;
	}
	else {
		if (beresp.ttl < 24h) {
			if (beresp.http.Cache-Control ~ "(private|no-cache|no-store)") {
				set beresp.ttl = 60s;
			}
			else {
				set beresp.ttl = 24h;
			}
		}
	}
	# Wordpress stuff
	if (bereq.http.host ~ "spacecityweather.com") {
		if ( (!(bereq.url ~ "(wp-(login|admin)|login)")) ) {
			unset beresp.http.set-cookie;
			set beresp.ttl = 1h;
		}
	if (bereq.url ~ "\.(gif|jpg|jpeg|swf|ttf|css|js|flv|mp3|mp4|pdf|ico|png)(\?.*|)$") {
		set beresp.ttl = 365d;
	}
	return (deliver);
	}

	if (bereq.http.host ~ "scwstaging.bigdinosaur.org") {
		if ( (!(bereq.url ~ "(wp-(login|admin)|login)")) ) {
			unset beresp.http.set-cookie;
			set beresp.ttl = 1h;
		}
	if (bereq.url ~ "\.(gif|jpg|jpeg|swf|ttf|css|js|flv|mp3|mp4|pdf|ico|png)(\?.*|)$") {
		set beresp.ttl = 365d;
	}
	return (deliver);
	}
}

sub vcl_deliver {

	# Display hit/miss info
	if (obj.hits > 0) {
		set resp.http.X-Cache = "HIT";
	}
	else {
		set resp.http.X-Cache = "MISS";
	}
	# Remove the Varnish header
	unset resp.http.X-Varnish;
	unset resp.http.Via;
	unset resp.http.X-Powered-By;
	unset resp.http.Server;

	# HTTP headers for all sites
	set resp.http.X-Are-Dinosaurs-Awesome = "HELL YES";
	set resp.http.Server = "on fire";
	set resp.http.X-Hack = "don't hack me bro";
	set resp.http.Referrer-Policy = "strict-origin-when-cross-origin";
	set resp.http.Strict-Transport-Security = "max-age=31536000; includeSubDomains; preload;";
	set resp.http.X-Content-Type-Options = "nosniff";
	set resp.http.X-XSS-Protection = "1; mode=block";
	set resp.http.X-Frame-Options = "DENY";
	set resp.http.Expect-CT = {"Expect-CT: max-age=0; report-uri="https://bigdino.report-uri.io/r/default/ct/reportOnly""};

	# Site-specific HTTP headers

	if (req.http.host ~ "analytics.bigdinosaur.net" ) {
		set resp.http.Public-Key-Pins = {"pin-sha256="7IjrQab9uQmCR98M+b3EYhC/G2GF4hZkJBHXv8xs9Sw="; pin-sha256="qvjy4gWppACpa7eDZaJEsC67Lt4hxSnmkoNvlwqGJ9I="; pin-sha256="iPUIMTeJlbpStrWLzZuiXGYziGAmkaDO38iqFcrmSks="; max-age=5270400"};
	}

	if (req.http.host ~ "fangs.ink" ) {
		set resp.http.Content-Security-Policy = "default-src https:; img-src 'self' https: data:; object-src 'none'; script-src 'self' https://analytics.bigdinosaur.net https://ajax.googleapis.com 'unsafe-inline'; font-src 'self'; upgrade-insecure-requests; frame-ancestors 'none'";
	}

	if (req.http.host ~ "www.bigdinosaur.org" ) {
		set resp.http.Content-Security-Policy = "default-src https:; img-src 'self' https: data:; object-src 'none'; script-src 'self' https://analytics.bigdinosaur.net https://ajax.googleapis.com 'unsafe-inline'; font-src 'self'; upgrade-insecure-requests; frame-ancestors 'none'";
		set resp.http.Public-Key-Pins = {"pin-sha256="lgAwgoBnOE6uzNzWihPrkn0stRz0rvUswt866yMdtmw="; pin-sha256="4VbipRWUeLo8SW6owquZ5xinWJcJd4mlTTz0fHyvinI="; pin-sha256="kHV6O3JCuQUmy6Nrn7sH/ZxDXMIr2BgY+DhZGe+bB+w="; max-age=5270400"};
	}

	if (req.http.host ~ "discourse.bigdinosaur.org" ) {
		set resp.http.Public-Key-Pins = {"pin-sha256="c9uxTwWtICkYpcdzLKrxYKKDfTJdCr2vCjUEaJHnI1M="; pin-sha256="n7u8UYS4WE/UAvm+CgHAmHzttL0iNaFNatLyuDcTHjY="; pin-sha256="RF/QlRsg/RTGqGxaElP5onv254RI/N4RcWF+DcF6ugI="; max-age=5270400"};
	}

	if (req.http.host ~ "blog.bigdinosaur.org" ) {
		set resp.http.Content-Security-Policy = "default-src https:; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; object-src 'none'; script-src 'self' https://analytics.bigdinosaur.net https://ajax.googleapis.com 'unsafe-inline' 'unsafe-eval'; font-src 'self'; upgrade-insecure-requests; frame-ancestors 'none'";
		set resp.http.Public-Key-Pins = {"pin-sha256="OgTIdBRPZ0StuwYBTf4kkCzAvp8e4+uTr2/qVhtnRVY="; pin-sha256="0omV1UV4TeKqplA30kW/wsKwycISOfoyj+6sSWYkNdQ="; pin-sha256="YOdesi5bRtcos8t7BLGMT+1A4EqMILtB+xKCuQlC/V8="; max-age=5270400"};
	}

	if (req.http.host ~ "littlel.bigdinosaur.org" ) {
		set resp.http.Content-Security-Policy = "default-src https:; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; object-src 'none'; script-src 'self' https://analytics.bigdinosaur.net https://ajax.googleapis.com 'unsafe-inline' 'unsafe-eval'; font-src 'self'; upgrade-insecure-requests; frame-ancestors 'none'";
		set resp.http.Public-Key-Pins = {"pin-sha256="VNZlldNbd3iqs4L/GttG2l4QTG6VnZnEHY3dl9GtLbU="; pin-sha256="FCH7blALTehFFYxVfvTcdgumNkUhF6XH7yKuMq7oPCk="; pin-sha256="w3COPbOlJqkTtI+xOFvIf8IDbDt+VztB4EE1b6F8uvE="; max-age=5270400"};
	}

	if (req.http.host ~ "ghostbetax.bigdinosaur.org" ) {
		set resp.http.Content-Security-Policy = "default-src https:; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; object-src 'none'; script-src 'self' https://analytics.bigdinosaur.net https://ajax.googleapis.com 'unsafe-inline' 'unsafe-eval'; font-src 'self'; upgrade-insecure-requests; frame-ancestors 'none'";
		set resp.http.Public-Key-Pins = {"pin-sha256="RllgTTGr2ZRjcPTxbk5/EX2D8VOHI17mezzCL0GrVzk="; pin-sha256="9CVGhomBzc/PYiUr0YFxf7OrdLcjg7FZGdwWDKEU+/I="; pin-sha256="q5h63G3NyICfDpuqwAMf/sfwmj03IGcdb/Krk6LespU="; max-age=5270400"};
	}

	if (req.http.host ~ "www.chroniclesofgeorge.com" ) {
		set resp.http.Content-Security-Policy = "default-src https:; style-src 'self'; img-src 'self' https: data:; object-src 'none'; script-src 'self' https://analytics.bigdinosaur.net https://ajax.googleapis.com 'unsafe-inline'; font-src 'self'; upgrade-insecure-requests; frame-ancestors 'none'";
	}

	if (req.http.host ~ "bigsaur.us" ) {
		set resp.http.Content-Security-Policy = "default-src https:; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; object-src 'none'; script-src 'self' https://analytics.bigdinosaur.net https://ajax.googleapis.com 'unsafe-inline'; font-src 'self'; upgrade-insecure-requests; frame-ancestors 'none'";
		set resp.http.Public-Key-Pins = {"pin-sha256="x60PszbM/rSZBeBCtPlAC3UIWVDpVZoDpuuyPPb2J6E="; pin-sha256="wgV1eT8sz2lqFcH5Wu0JWopwly8gTgSiUQ9wiDfbGBk="; pin-sha256="1LV+BQyUrM1PukrlxxlEDlvBt5N4isPYwkUFQMSC4i8="; max-age=5270400"};
	}

	if (req.http.host ~ "mastodon.bigdinosaur.org" ) {
		set resp.http.Content-Security-Policy = "frame-ancestors 'none'; default-src 'none'; script-src 'self'; object-src 'self'; style-src 'self'; img-src * data:; media-src 'self' data:; frame-src 'none'; font-src 'self'; connect-src 'self'";
		set resp.http.Public-Key-Pins = {"pin-sha256="oKKV1De8F3DU8WJZZSn1ExtvFZL2vw3rUESMFon9vTQ="; pin-sha256="85HrS44JoXe7pxDliPg3nEnSyopkCUA/EinUY9+gFBQ="; pin-sha256="atLc1BxYtsMn2GD+czAAb5WYGKA26TfV3QjbwkpeLw4="; max-age=5270400"};
	}

	# Remove custom error header
	unset resp.http.MyError;
	return (deliver);
}

sub vcl_synth {

	if (resp.status == 750) {
		set resp.status = 404 ;
		return(deliver);
	}

	if (resp.status == 405) {
		set resp.http.Content-Type = "text/html; charset=utf-8";
		set resp.http.MyError = std.fileread("/var/www/error/varnisherr.html");
		synthetic(resp.http.MyError);
		return(deliver);
	}
}
