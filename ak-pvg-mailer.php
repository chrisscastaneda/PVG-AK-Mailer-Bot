<?php
// session_start();  # not really using sessions in current iteration of this script
include( 'Mailchimp.php' );

if ( isset($_POST['subject']) && isset( $_POST['pvgurls'] )&& isset( $_POST['akuser'] ) && isset( $_POST['akpass'] ) ) {
  # INITIALIZE and PROCESS $_POST variables
  $ak_user      = $_POST['akuser'];
  $ak_pass      = $_POST['akpass'];
  $subject		= $_POST['subject'];
  $pvg_urls_str = $_POST['pvgurls'];

  # SET  $pvg_urls;
  $pvg_urls;    // matrix to be parsed from $pvg_urls_str
  $pvg_urls_ary = explode( "\n", $pvg_urls_str );
  foreach ($pvg_urls_ary as $key => $value) {
    $mail = array();
    $boom = explode( ",", $value );
    $mail['url'] = $boom[0];
    if (isset($boom[1]))	$mail['id'] = $boom[1];
    $pvg_urls[] = $mail;
  }

  // echo nl2br( $pvg_urls_str ) . "<br> $ak_user <br> $ak_pass";
  // echo '<pre>';
  // print_r( $pvg_urls );
  // echo '</pre>';

  # HELPER FUNCTIONS
  function getResponseHeader($header) {
    foreach ($response as $key => $r) {
       if (stripos($r, $header) !== FALSE) {
          list($headername, $headervalue) = explode(":", $r);
          return trim($headervalue);
       }
    }
  }
  function http_POST( $url, $fields_ary ){
    //url-ify the data for the POST
    foreach($fields_ary as $key=>$value) { $fields_str .= $key.'='.$value.'&'; }
    rtrim($fields_str, '&');    
    //open connection
    $ch = curl_init();
    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_POST, count($fields));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_str);
    //execute post
    $result = curl_exec($ch);
    //close connection
    curl_close($ch);
    return $result;
  }
  function http_POST_json( $url, $fields_ary ){
    $fields_json = json_encode( $fields_ary );

    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_POST, count($fields));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_json);

    //execute post
    $result = curl_exec($ch);

    //close connection
    curl_close($ch);

    echo '<h2 style="color:#0f0">post result</h2><pre style="border:2px solid #00f;padding:10px;margin:20px;">' . $result . '</pre>';


    return $result;
  }
  function http_GET( $url ) {
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, $url);
    curl_setopt ($ch, CURLOPT_HEADER, 0);
    ob_start();
    curl_exec ($ch);
    curl_close ($ch);
    $content = ob_get_contents();
    ob_end_clean(); 
    return $content;     
  }
  function plain_text( $ld_param ){
	$text = http_get ( 'http://progressivevotersguide.com/textmail' );

# expects $ld_param in the form 'ld=26th'
    // $text = "Dear Friend,\n\nIs your ballot on the coffee table, in your purse, or somewhere else? Tuesday is the last day to vote in the primary election, so be sure to place your stamp or drop it off at a ballot box immediately!\n\nVoting records show you're the kind of person who votes, so we're sending you the Progressive Voters Guide with useful information about candidates and ballot measures along with the endorsements of Washington's leading progressive organizations. Thousands of people are voting every day, so don’t get left out of this critical election.\n\nProgressiveVotersGuide.com/?$ld_param&src=WAMailText\n\nIf you’ve already voted, thank you! Please share this guide with your friends, family, and coworkers and encourage them to vote too.\n\nThey can find their own locally customized version at\n\nProgressiveVotersGuide.com?src=WAMailText\n\nThanks for all that you do (especially voting),\n\nAaron and the entire team at Fuse";
    return $text;
  }
  function ak_rest_login( $user, $pass ){
    #https://chris:1FthePnwa@fusewashington.actionkit.com/rest/v1/user/
    $url = 'https://fusewashington.actionkit.com/rest/v1/user/';
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_USERPWD => $user.':'.$pass, 
      CURLOPT_HEADER => true,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_URL => $url,
      CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)'
    ));
    $response = curl_exec($curl);
    $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return $http_status;
  }
  
  function set_ak_mailing_subject( $subject="Your Progressive Voters Guide", $mailing_id, $user, $pass ){
    // Your Progressive Voters Guide for the primary election
    $url = 'https://fusewashington.actionkit.com/rest/v1/mailingsubject/';
    $data = array(
      "text" => $subject, 
      "mailing" => "/rest/v1/mailing/$mailing_id/"
    );                                                                    
    $data_json = json_encode( $data );                                                                                   
    $ch = curl_init( $url );                                                
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_VERBOSE, true );
    curl_setopt( $ch, CURLOPT_HEADER, true );
    curl_setopt( $ch, CURLOPT_USERPWD, $user . ':' . $pass );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen( $data_json ) 
      )
    );  
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_json );                                                                                                                 
    $header = curl_exec($ch);
    $http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close($ch);
    return $header;
  }
  
  function generate_ak_mailing( $subject, $html, $id = 0, $identifier='*', $user, $pass ){
    $header = '';
    $result = '';
    $draft_url = 'https://fusewashington.actionkit.com/mailings/drafts/';
    $http_status = '';
    $http_method = 'POST';

    $url = "https://fusewashington.actionkit.com/rest/v1/mailing/";
    if ( $id != 0 && $id != '' ) { 
      $url .= $id . '/'; 
      $http_method = 'PUT';
    }
    // echo "<div style='border:5px solid #0f0; padding:5px;'>$http_method - $id</div>";
    // echo "<div style='border:5px dashed #000; padding:5px;'>$url</div>";
    // echo "<div style='border:5px solid #f00; padding:5px;'>$identifier</div>";
    // echo "<div style='border:10px solid #00f;width:100%;'>$html</div>";

    $data = array(
      "html" => $html, 
      "notes" => "[BOT] PVG 2014 General - First Send - $identifier",
      "custom_fromline" => "\"Aaron Ostrom, Fuse Votes\" <info@fusewashington.org>",
      "reply_to" => "info@fusewashington.org",
      "text" => plain_text( $identifier )
    );                                                                    
    $data_json = json_encode( $data );                                                                                   
    $ch = curl_init( $url );                                                
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $http_method );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_VERBOSE, true );
    curl_setopt( $ch, CURLOPT_HEADER, true );
    curl_setopt( $ch, CURLOPT_USERPWD, $user . ':' . $pass );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen( $data_json ) 
      )
    );  
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_json );                                                                                                                 
    $header = curl_exec($ch);
    $http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $header_ary = explode( "\n", $header );
	$headers;
    foreach ($header_ary as $key => $value) {
      $temp = explode( ": ", $value );
	  if (count($temp) == 2)
	      $headers[ trim( $temp[0] ) ] = trim( $temp[1] );
    }

    // echo "<div style='border:5px solid #f00; padding:5px;'>$http_status</div>";
    // echo "<pre style='border:5px solid #0f0; padding:5px;'>";
    //   print_r( $headers );
    // echo "</pre>";
    // echo "<pre style='border:5px solid #00f; padding:5px'>$header</pre>";
    // echo "<div style='border:5px dashed #f11; padding:5px;' >". $headers['Content-Type']."</div>";
    // if( isset( $headers['Location'] ) ){
    //   echo '<h2 style="color:#f00;">' . end( explode( '/', chop( $headers['Location'], '/' ) ) ) . '</h2>';
    //   echo "<div style='border:5px dashed #11f; padding:5px;' >".$headers['Location']."</div>";
    // }
    if( isset( $headers['Location'] ) ){ 
	  $id = end( explode( '/', chop( $headers['Location'], '/' ) ) ); 
	}
	
    set_ak_mailing_subject( $subject, $id, $user, $pass );
    $draft_url .= $id . '/';

    curl_close($ch);

    // return $result;
    return $draft_url;
  }

  function get_pvg_content( $url ){
    $raw_html = '<p>raw PVG mail content</p>';
    return $raw_html;
  }


  # MAIN FUNCTION
  /** 
   * BLUEPRINT:
   * for each mail_url
   *   GET raw_html from progressivevotersguide.com/mail?ld=...1-49
   *   inline_html = POST raw_html to mailchimp-css-inliner 
   *   POST inline_html to https://fusewashington.actionkit.com/rest/v1/mailing/
   */
  function main( $subject, $pvg_urls, $ak_user, $ak_pass ){
    $login_status = ak_rest_login( $ak_user, $ak_pass );
    if ( $login_status == 200 ) {
      echo '<pre>';
      print_r( $pvg_urls );
      echo '</pre>';
      $monkey = new Mailchimp( 'fc26b67691fed4841cd90438b799d303-us8' );

      foreach ($pvg_urls as $mail) {
        $raw_html = http_GET( $mail['url'] );
        $identifier = explode( '?', $mail['url'] );
		    $identifier = $identifier['1'];
        // $inlined_html = inline_css( $raw_html );
        $inlined_html = $monkey->helper->inlineCss( $raw_html, true );
        // echo '<!-- START INLINE --><div style="border:10px solid #f00;width:100%;">' . $inlined_html['html'] . '</div><!-- END INLINE -->';
		  if (isset($mail['id']))  $id = $mail['id']; else $id = ''; 
        $draft_url = generate_ak_mailing( $subject, $inlined_html['html'], $id, $identifier, $ak_user, $ak_pass );
        echo "<p><a href='$draft_url' target='_blank'>$draft_url</a></p>";
      }
    } else {
      echo "<h2 style='color:#d00;'>Login Error: $login_status</h2>";
    }
  }
  main( $subject, $pvg_urls, $ak_user, $ak_pass );
} else { # else NOT POST
?>
<!DOCTYPE html>
<html>
  <head>
    <title>PVG BOT</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <!-- <link href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet"> -->
    <link rel="stylesheet" href="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css" />

    <style type="text/css">
      .container { width: 85%; margin-right: auto; margin-left: auto; }
      #pvgurls { width: 100%; }
      .form-group, textarea { margin-bottom: 15px; }
      #output { width: 100%; min-height: 250px; border: 1px solid #666; margin-top: 50px; overflow: scroll; padding: 10px;}
      
      /* start css spinner/loader code */
      #my-spinner { text-align: center; }
      .spinner { margin: 10px auto 0; width: 70px; text-align: center; }
      .spinner > div {
        width: 18px; height: 18px; background-color: #333; border-radius: 100%; display: inline-block;
        -webkit-animation: bouncedelay 1.4s infinite ease-in-out;
        animation: bouncedelay 1.4s infinite ease-in-out;
        /* Prevent first frame from flickering when animation starts */
        -webkit-animation-fill-mode: both;
        animation-fill-mode: both;
      }
      .spinner .bounce1 { -webkit-animation-delay: -0.32s; animation-delay: -0.32s; }
      .spinner .bounce2 { -webkit-animation-delay: -0.16s; animation-delay: -0.16s; }
      @-webkit-keyframes bouncedelay {
        0%, 80%, 100% { -webkit-transform: scale(0.0) }
        40% { -webkit-transform: scale(1.0) }
      }
      @keyframes bouncedelay {
        0%, 80%, 100% { 
          transform: scale(0.0);
          -webkit-transform: scale(0.0);
        } 40% { 
          transform: scale(1.0);
          -webkit-transform: scale(1.0);
        }
      }    
      /* end css spinner/loader code */
    </style>
  </head>
  <body>
    <div class="container">
      <h1>AK PVG Mailer</h1>
      <p><b>How to use:</b>
        <ul>
          <li>enter the url for source of mail content, one per line</li>
          <li>(optional) after the url enter a comma then an Action Kit mailing ID<br>
            <i>if no mailing ID is listed then will create a new mailing</i><br>
            <i>if mailing ID is listed, will write over the current content</i></li>
          <li>(WARNING) does not check user input and is probably vulnerable to code injection</li>
          <li>Action Kit user account must have REST API permission enabled</li>
        </ul>
        <br><br>
        Examples:
      </p>
      <pre>http://progressivevotersguide.com/mail?ld=34th,1141
http://progressivevotersguide.com/mail?ld=1st,1124
http://progressivevotersguide.com/mail?ld=2nd</pre>
      <form id="mailer" class="form-inline" role="form" 
            action="ak-pvg-mailer.php"
            onSubmit="this.submitted=1;return false">
		<textarea id="subject" name="subject" class="form-control" rows="1" cols="80" placeholder="subject line"></textarea>    
        <textarea id="pvgurls" name="pvgurls" class="form-control" rows="10" placeholder="enter urls, mailing id (optional)"></textarea>
        <br>
        <div class="form-group credentials">
          <label class="sr-only" for="ak-user">ActionKit Login</label>
          <input id="akuser" name="akuser" type="text" class="form-control" placeholder="Enter ActionKit Username">
        </div>
        <div class="form-group credentials">
          <label class="sr-only" for="ak-pass">ActionKit Password</label>
          <input id="akpass" name="akpass" type="password" class="form-control" placeholder="ActionKit Password">
        </div>
        <button id="generate-mail" type="submit" class="btn btn-default">Generate Mail</button>
      </form>
      <div id="my-spinner">&nbsp;</div>
      <div id="output"></div>
    </div><!-- /.container -->
    <!-- SCRIPTS AT THE BOTTOM ============================================= -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
    <script type="text/javascript">
      (function($) {
        $('#generate-mail').click( function() {
          var args = {
            subject: $('#subject').val(),
            pvgurls: $('#pvgurls').val(),
            akuser: $('#akuser').val(),
            akpass: $('#akpass').val()
          }
          $(this).html('Generating...');
          $('#my-spinner').html('<div class="spinner"><div class="bounce1"></div><div class="bounce2"></div><div class="bounce3"></div></div>');
          
          $.post( 'ak-pvg-mailer.php', args, function(data) {  
            $('#output').html( data );
            $('#generate-mail').html('Generate More Mail');
            $('#my-spinner').html('&nbsp;');
          });
          // $(this).html('Generate More Mail');
        });
      })(jQuery);
    </script>
  </body>
</html>
<?php
} // end else if POST
?>





