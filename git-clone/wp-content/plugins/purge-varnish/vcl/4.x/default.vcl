# This is a basic VCL configuration file for varnish.  See the vcl(7)
# man page for details on VCL syntax and semantics.
#
# 
vcl 4.0;

import std;
import directors;

# Default backend definition.  Set this to point to your content
# server.
# https://fourkitchens.atlassian.net/wiki/display/TECH/Configure+Varnish+3+for+Drupal+7

backend techcircle {
  # App server IP.
  .host = "127.0.0.1";
  .port = "80";
  .connect_timeout = 300s;
  .first_byte_timeout = 300s;
  .between_bytes_timeout = 300s; 
  .max_connections = 2000;
}

#backend others {
#   App server IP.
#  .host = "127.0.0.2";
#  .port = "80";
#  .connect_timeout = 300s;
#  .first_byte_timeout = 300s;
#  .between_bytes_timeout = 300s; 
#  .max_connections = 3000;
#}

sub vcl_init {
    #Called when VCL is loaded, before any requests pass through it.
    #Typically used to initialize VMODs.

    new vdir_vcc_backend = directors.round_robin();
    vdir_vcc_backend.add_backend(techcircle); 

    #new vdir_others_backend = directors.round_robin();
    #vdir_apps_backend.add_backend(others);
}

# Respond to incoming requests.
sub vcl_recv {

    # Happens before we check if we have this in cache already.
    #
    # Typically you clean up the request here, removing cookies you don't need,
    # rewriting the request, etc.

    # Default backend
    set req.backend_hint = vdir_vcc_backend.backend();

    # Normalize the header, remove the port (in case you're testing this on various TCP ports)
    set req.http.Host = regsub(req.http.Host, ":[0-9]+", "");

    # ONLY CACHE GET AND HEAD REQUESTS
    # ##########################################################
    if (req.method != "GET" && req.method != "HEAD") {
      return (pass);
    }

    #PIPE ALL NON-STANDARD REQUESTS
    # ##########################################################
    if (req.method != "GET" &&
      req.method != "HEAD" &&
      req.method != "PUT" &&
      req.method != "POST" &&
      req.method != "TRACE" &&
      req.method != "OPTIONS" &&
      req.method != "DELETE") {
      return (pipe);
    }
    
    # Normalize the query arguments (the if condition exists so that we don't normalize the url for wordpress
    if (req.url !~ "load-scripts\.php") {
      set req.url = std.querysort(req.url);
    }

    # For all static assets unset cookies
    if (req.url ~ "\.(pdf|asc|dat|txt|doc|xls|ppt|tgz|csv|gif|jpg|jpeg|swf|ttf|css|js|flv|mp3|mp4|ico|png|woff|xml)(\?.*|)$") {
      unset req.http.Cookie;
      set req.url = regsub(req.url, "\?.*$", "");
    }

    # Some generic URL manipulation, useful for all templates that follow
    # First remove the Google Analytics added parameters, useless for our backend
    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Strip hash, server doesn't need it.
    if (req.url ~ "\#") {
      set req.url = regsub(req.url, "\#.*$", "");
    }

    # Strip a trailing ? if it exists
    if (req.url ~ "\?$") {
      set req.url = regsub(req.url, "\?$", "");
    }

    # Large static files are delivered directly to the end-user without
    # waiting for Varnish to fully read the file first.
    # Varnish 4 fully supports Streaming, so set do_stream in vcl_backend_response()
    if (req.url ~ "^[^?]*\.(mp[34]|rar|tar|tgz|gz|wav|zip|bz2|xz|7z|avi|mov|ogm|mpe?g|mk[av]|webm)(\?.*)?$") {
        unset req.http.Cookie;
        return (hash);
    }

    # Send Surrogate-Capability headers to announce ESI support to backend
    set req.http.Surrogate-Capability = "key=ESI/1.0";


    # Send to backend server if the request is wordpress login page, admin or preview.
    if (req.url ~ "wp-(login|admin)" || req.url ~ "preview=true" || req.url ~ "xmlrpc.php" || req.url ~ "preview_id=") {
      return (pass);
    }

    # Send to backend if phpmyadmin is being accessed
    if(req.url ~ "^/myphp/.*$" || req.url ~ "^/myphp$") 
    {
      return(pass);
    }

    # Send to backend server Drupal backend requests
    if (req.url ~ "^/wp-(login|admin)" ||
    req.url ~ "^/wp-cron\.php$" ||
    req.url ~ "^/post\.php$" ||
    req.url ~ "^/kill-sleep-query\.php$" ||
    req.url ~ "^/djfeed\.php$" ||
    req.url ~ "^/xmlrpc\.php$" ||
    req.url ~ "^/myphp$" ||
    req.url ~ "^/wp-includes/.*$" ||
    req.url ~ "^/myphp/.*$" ||
    req.url ~ "^/admin" ||
    req.url ~ "^/admin/.$" ||
    req.url ~ "/feed/")
    {
        return (pass);
    }

    if (req.restarts == 0) {
      if (req.http.X-Forwarded-For) { # set or append the client.ip to X-Forwarded-For header
        set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
      } else {
        set req.http.X-Forwarded-For = client.ip;
      }
    }

    # Remove has_js and Google Analytics __* cookies.
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_[_a-z]+|has_js)=[^;]*", "");

    # Remove a ";" prefix in the cookie if present
    set req.http.Cookie = regsuball(req.http.Cookie, "^;\s*", "");

    # Are there cookies left with only spaces or that are empty?
    if (req.http.Cookie ~ "^\s*$") {
        unset req.http.Cookie;
    }

    # Remove all cookies that Drupal doesn't need to know about. We explicitly
    # list the ones that Drupal does need, the SESS and NO_CACHE. If, after
    # running this code we find that either of these two cookies remains, we
    # will pass as the page cannot be cached.
    if (req.http.Cookie) {
      # 1. Append a semi-colon to the front of the cookie string.
      # 2. Remove all spaces that appear after semi-colons.
      # 3. Match the cookies we want to keep, adding the space we removed
      #    previously back. (\1) is first matching group in the regsuball.
      # 4. Remove all other cookies, identifying them by the fact that they have
      #    no space after the preceding semi-colon.
      # 5. Remove all spaces and semi-colons from the beginning and end of the
      #    cookie string.
      set req.http.Cookie = ";" + req.http.Cookie;
      set req.http.Cookie = regsuball(req.http.Cookie, "; +", ";");
      set req.http.Cookie = regsuball(req.http.Cookie, ";(SESS[a-z0-9]+|SSESS[a-z0-9]+|NO_CACHE|Drupal.visitor.no_cache)=", "; \1=");
      set req.http.Cookie = regsuball(req.http.Cookie, ";[^ ][^;]*", "");
      set req.http.Cookie = regsuball(req.http.Cookie, "^[; ]+|[; ]+$", "");

      if (req.http.Cookie == "") {
        # If there are no remaining cookies, remove the cookie header. If there
        # aren't any cookie headers, Varnish's default behavior will be to cache
        # the page.
        unset req.http.Cookie;
      }
      else {
        # If there is any cookies left (a session or NO_CACHE cookie), do not
        # cache the page. Pass it on to Apache directly.
        return (pass);
      }
    }

    if (req.http.Authorization) {
      # Not cacheable by default
      return (pass);
    }

    /* Standardise Accept-Encoding */
    if (req.http.Accept-Encoding) {
      if (req.url ~ "\.(jpg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|woff|ttf)$") {
        unset req.http.Accept-Encoding; # No point in compressing these
      } elseif (req.http.Accept-Encoding ~ "gzip") {
        set req.http.Accept-Encoding = "gzip";
      } elseif (req.http.Accept-Encoding ~ "deflate") {
        set req.http.Accept-Encoding = "deflate";
      } else {
        # unkown algorithm
        unset req.http.Accept-Encoding;
      }
    }

    return (hash);
  }

# The data on which the hashing will take place
sub vcl_hash {
    # Called after vcl_recv to create a hash value for the request. This is used as a key
    # to look up the object in Varnish.
    hash_data(req.url);

    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }
}

sub vcl_hit {
    # Called when a cache lookup is successful.
    if (obj.ttl >= 0s) {
        # A pure unadultered hit, deliver it
        return (deliver);
    }

    # https://www.varnish-cache.org/docs/trunk/users-guide/vcl-grace.html
    # When several clients are requesting the same page Varnish will send one request to the backend and place the others on hold while fetching one copy from the backend. In some products this is called request coalescing and Varnish does this automatically.
    # If you are serving thousands of hits per second the queue of waiting requests can get huge. There are two potential problems - one is a thundering herd problem - suddenly releasing a thousand threads to serve content might send the load sky high. Secondly - nobody likes to wait. To deal with this we can instruct Varnish to keep the objects in cache beyond their TTL and to serve the waiting requests somewhat stale content.
    # if (!std.healthy(req.backend_hint) && (obj.ttl + obj.grace > 0s)) {
    #   return (deliver);
    # } else {
    #   return (fetch);
    # }

    # We have no fresh fish. Lets look at the stale ones.
    if (std.healthy(req.backend_hint)) {
    # Backend is healthy. Limit age to 10s.
        if (obj.ttl + 10s > 0s) {
            #set req.http.grace = "normal(limited)";
            return (deliver);
        } else {
            # No candidate for grace. Fetch a fresh object.
            return(fetch);
        }
    } else {
        # backend is sick - use full grace
        if (obj.ttl + obj.grace > 0s) {
            #set req.http.grace = "full";
            return (deliver);
        } else {
            # no graced object.
            return (fetch);
        }
    }
    # fetch & deliver once we get the result
    return (fetch); # Dead code, keep as a safeguard
}

sub vcl_deliver {
    if (obj.hits > 0) { # Add debug header to see if it's a HIT/MISS and the number of hits, disable when not needed
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }

    # Please note that obj.hits behaviour changed in 4.0, now it counts per objecthead, not per object
    # and obj.hits may not be reset in some cases where bans are in use. See bug 1492 for details.
    # So take hits with a grain of salt
    set resp.http.X-Cache-Hits = obj.hits;

    # Happens when we have all the pieces we need, and are about to send the
    # response to the client.
    #
    # You can do accounting or modifying the final object here.

    # Remove some headers: Apache version & OS
    unset resp.http.Server;
    #unset resp.http.X-Drupal-Cache;
    #unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.Link;
    unset resp.http.X-Generator;
    unset resp.http.X-Powered-By;
    return (deliver);
}


sub vcl_fini {
    # Called when VCL is discarded only after all requests have exited the VCL.
    # Typically used to clean up VMODs.
    return (ok);
}

sub vcl_backend_response {

    # Do not cache 400s and 500s
    if ( beresp.status >= 400 ) {
        set beresp.uncacheable = true;
        set beresp.http.X-Cacheable = "NO: beresp.status";
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Happens after we have read the response headers from the backend.
    #
    # Here you clean the response headers, removing silly Set-Cookie headers
    # and other mistakes your backend does.

    # Pause ESI request and remove Surrogate-Control header
    if (beresp.http.Surrogate-Control ~ "ESI/1.0") {
        unset beresp.http.Surrogate-Control;
        set beresp.do_esi = true;
    }

    # Enable cache for all static files
    # The same argument as the static caches from above: monitor your cache size, if you get data nuked out of it, consider giving up the static file cache.
    # Before you blindly enable this, have a read here: https://ma.ttias.be/stop-caching-static-files/
    if (bereq.url ~ "^[^?]*\.(bmp|bz2|css|doc|eot|flv|gif|gz|ico|jpeg|jpg|js|less|mp[34]|pdf|png|rar|rtf|swf|tar|tgz|txt|wav|woff|xml|zip|webm)(\?.*)?$") {
         unset beresp.http.set-cookie;
         set beresp.do_gzip = false;
    }

    # Large static files are delivered directly to the end-user without
    # waiting for Varnish to fully read the file first.
    # Varnish 4 fully supports Streaming, so use streaming here to avoid locking.
    if (bereq.url ~ "^[^?]*\.(mp[34]|rar|tar|tgz|gz|wav|zip|bz2|xz|7z|avi|mov|ogm|mpe?g|mk[av]|webm)(\?.*)?$") {
        unset beresp.http.set-cookie;
        set beresp.do_stream = true;  # Check memory usage it'll grow in fetch_chunksize blocks (128k by default) if the backend doesn't send a Content-Length header, so only enable it for big objects
        set beresp.do_gzip = false;   # Don't try to compress it for storage
    }

    # Sometimes, a 301 or 302 redirect formed via Apache's mod_rewrite can mess with the HTTP port that is being passed along.
    # This often happens with simple rewrite rules in a scenario where Varnish runs on :80 and Apache on :8080 on the same box.
    # A redirect can then often redirect the end-user to a URL on :8080, where it should be :80.
    # This may need finetuning on your setup.
    #
    # To prevent accidental replace, we only filter the 301/302 redirects for now.
    if (beresp.status == 301 || beresp.status == 302) {
        set beresp.http.Location = regsub(beresp.http.Location, ":[0-9]+", "");
    }

    # Allow stale content, in case the backend goes down.
    # make Varnish keep all objects for 6 hours beyond their TTL
    set beresp.grace = 6h;

    if (bereq.method != "GET")
    {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        set beresp.http.X-Cacheable = "NO: Not a GET request";
        return (deliver);
    }

    if (bereq.http.Cookie ~ "wordpress_logged_in_.*=[^;]+(;)?") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        set beresp.http.X-Cacheable = "NO: Wordpress loggedin";
        return (deliver);
    }

    if (bereq.url ~ "wp-(login|admin)" || bereq.url ~ "preview=true" || bereq.url ~ "xmlrpc.php" || bereq.url ~ "preview_id=") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        set beresp.http.X-Cacheable = "NO: Wordpress admin";
        return (deliver);
    }
    
    # Set default cache timeout
    set beresp.ttl = 1h;
    # Set cache timeout for InfraCircle home page
    if(bereq.url ~ "^(http://stechcircle|http://techcircle)?" && (bereq.url ~ "vccircle.com/$" || bereq.url ~ "vccircle.com$")){
      set beresp.ttl = 5m;
    }
    
    return (deliver);
}

# In the event of an error, show friendlier messages.
sub vcl_backend_error {
  # Redirect to some other URL in the case of a homepage failure.
  #if (req.url ~ "^/?$") {
  #  set obj.status = 302;
  #  set obj.http.Location = "http://backup.example.com/";
  #}
  return (retry); 
}

