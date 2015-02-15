<?php
/*Controls registration form behavior on the front end*/
global $wpdb;
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
wp_enqueue_style( 'crf-style-simple', plugin_dir_url(__FILE__) . 'css/crf-style-simple.css');
$textdomain = 'custom-registration-form-pro-with-submission-manager';
$crf_forms =$wpdb->prefix."crf_forms";
$crf_fields =$wpdb->prefix."crf_fields";
$path =  plugin_dir_url(__FILE__);
$crf_option=$wpdb->prefix."crf_option";
$crf_entries =$wpdb->prefix."crf_entries";
$select="select `value` from $crf_option where fieldname='enable_captcha'";
$enable_captcha = $wpdb->get_var($select);
$qry="select `custom_text` from $crf_forms where id=".$content['id'];
$custom_text = $wpdb->get_var($qry);
$qry="select `form_type` from $crf_forms where id=".$content['id'];
$form_type = $wpdb->get_var($qry);
$qry="select `form_name` from $crf_forms where id=".$content['id'];
$form_name = $wpdb->get_var($qry);

$qry="select `value` from $crf_option where fieldname='from_email'";
$from_email_address = $wpdb->get_var($qry);
if($from_email_address=="")
{
	$from_email_address = get_option('admin_email');	
}

if($form_type=='reg_form')
{
wp_enqueue_script( 'mocha.js',  plugin_dir_url(__FILE__) . 'js/mocha.js');	
}

$qry="select `value` from $crf_option where fieldname='autogeneratedepass'";
$pwd_show = $wpdb->get_var($qry);
$qry="select `value` from $crf_option where fieldname='userautoapproval'";
$autoapproval = $wpdb->get_var($qry);
if($enable_captcha=='yes')
{
	$qry="select `value` from $crf_option where fieldname='public_key'";
	$publickey = $wpdb->get_var($qry);
	$qry="select `value` from $crf_option where fieldname='private_key'";
	$privatekey = $wpdb->get_var($qry);
	require_once('recaptchalib.php');//Displays recaptcha
	# the response from reCAPTCHA
	$resp = null;
	# the error code from reCAPTCHA, if any
	$error = null;
	# was there a reCAPTCHA response?
	if (isset($_POST["recaptcha_response_field"]))
	{
		$resp = recaptcha_check_answer ($privatekey,
		$_SERVER["REMOTE_ADDR"],
		$_POST["recaptcha_challenge_field"],
		$_POST["recaptcha_response_field"]);
		if ($resp->is_valid)
		{
			$submit = 1;
		}
		else
		{
		?>
        <style type="text/css">
.error {
	border: 1px solid #00529B;
	padding-bottom: 15px;
	padding-top: 15px;
	color: #D8000C;
	background-color: #FFBABA;
}
</style>
        <!--HTML for showing error when recaptcha does not matches-->
<div class="error" align="center"> <?php _e( 'Sorry, you didn\'t enter the correct captcha code.', $textdomain ); ?> </div>
<br />
<br />
<br />
<?php
		$submit = 0;
		}
	}
}
else
{
	$submit=1;
}
if(isset($_POST['submit']) && $submit==1 ) // Checks if the submit button is pressed or not
{
	$qry1 = "select * from $crf_fields where Form_Id= '".$content['id']."' and Type not in('heading','paragraph') order by ordering asc";
	$reg1 = $wpdb->get_results($qry1);
	$entry= array();
	
	if($form_type=='reg_form')
	{
			$user_email = $_POST['user_email'];
			$entry['user_name'] =  $_POST['user_name'];
			$entry['user_email'] =  $_POST['user_email'];
			$entry['user_pass'] =  $_POST['inputPassword'];
			$entry['role']	= 'Subscriber';	
	}
	
	if(!empty($reg1))
	{
	 foreach($reg1 as $row1)
	 {
		if(!empty($row1))
		{
			/*file addon start */
			$Customfield = str_replace(" ","_",$row1->Name);
			
			if ( is_plugin_active('file-upload-addon/file-upload.php') && $row1->Type=='file') 
			{
				global $fileuploadfunctionality;
				$filefield = $_FILES[$Customfield];
				$fileuploadfunctionality = new fileuploadfuncitonality();
				$attach_id = $fileuploadfunctionality->save_form($filefield);
				$entry[$Customfield] =  $attach_id;
			}
			else
			if(isset($_POST[$Customfield]))
			{	
				$entry[$Customfield] =  $_POST[$Customfield];
			}
			/*file addon end */
		}
	 }
	}
	$entries = serialize($entry);
	$insert_entries = "insert into $crf_entries values('','".$content['id']."','".$form_type."','".$autoapproval."','".$entries."')";
	$wpdb->query($insert_entries);
	
	if($form_type=='reg_form' && $autoapproval=='yes')
	{
$user_name = $_POST['user_name']; // receiving username
$user_email = $_POST['user_email']; // receiving email address
$inputPassword = $_POST['inputPassword']; // receiving password
$user_confirm_password = $_POST['user_confirm_password']; // receiving confirm password
$user_id = username_exists( $user_name ); // Checks if username is already exists.

if ( !$user_id and email_exists($user_email) == false )//Creates password if password auto-generation is turned on in the settings
{
	if($pwd_show != "no")
	{
		$random_password = $inputPassword;
	}
	else
	{
		$random_password = $inputPassword;
	}
$user_id = wp_create_user( $user_name, $random_password, $user_email );//Creates new WP user after successful registration
$role = 'subscriber';
/*Insert custom field values if displayed in registration form*/
$qry1 = "select * from $crf_fields where Form_Id= '".$content['id']."' and Type not in('heading','paragraph') order by ordering asc";
$reg1 = $wpdb->get_results($qry1);
if(!empty($reg1))
{
 foreach($reg1 as $row1)
 {
	if(!empty($row1))
	{
		$Customfield = str_replace(" ","_",$row1->Name);
		if(!isset($prev_value)) $prev_value='';
		add_user_meta( $user_id, $Customfield, $_POST[$Customfield], true );
		update_user_meta( $user_id, $Customfield, $_POST[$Customfield], $prev_value );
	}
 }
}

/*Assigns user role to newly registered user*/
$role = 'subscriber';
$user_id = wp_update_user( array( 'ID' => $user_id, 'role' => $role ) );
}
else
{
	$random_password = __('User already exists.  Password inherited.',$textdomain);
?>
<!--HTML for displaying error when username already exists (This is different from error shown by jQuery validation.)-->
<div id="crf-form">
  <div id="main-crf-form">
    <div class="main-edit-profile" align="center"><?php _e( 'Sorry, the username or e-mail is already taken.', $textdomain ); ?><br />
      <br />
      <div align="center" style="width:430px;"> <a href="javascript:void(0);" onclick="javascript:history.back();" title="Registration">
        <div class="UltimatePB-Button"><?php _e( 'Go back to Registration.', $textdomain ); ?></div>
        </a> &nbsp; <a href="<?php echo site_url(); ?>">
        <div class="UltimatePB-Button"><?php _e( 'Go back to Home-Page.', $textdomain ); ?></div>
        </a> </div>
    </div>
  </div>
</div>
<?php
	}
	}
	$qry="SELECT success_message FROM $crf_forms WHERE id=".$content['id'];
	$success_message = $wpdb->get_var($qry);
	
	if($success_message=="")
	{
		$success_message = __('Thank you for your submission.',$textdomain);
	}
	$qry="SELECT redirect_option FROM $crf_forms WHERE id=".$content['id'];
	$redirect_option = $wpdb->get_var($qry);
	
	if($redirect_option=='url')
	{
		$qry="SELECT redirect_url_url FROM $crf_forms WHERE id=".$content['id'];
		$url = $wpdb->get_var($qry);	
	}
	
	if($redirect_option == 'page')
	{
		$qry="SELECT redirect_page_id FROM $crf_forms WHERE id=".$content['id'];
		$page_id = $wpdb->get_var($qry);	
		$url =  get_permalink($page_id); 
	}
	
	if($redirect_option!='none')
	{	
		header('refresh: 5; url='.$url);
	}
	?>
<div id="crf-form">
  <div id="main-crf-form">
    <div class="main-edit-profile"><?php echo $success_message;?><br />
      <br />
    </div>
  </div>
</div>
<?php
	  $qry="SELECT send_email FROM $crf_forms WHERE id=".$content['id'];//Fetches Auto Responder enable or disalbe.
	  $send_email = $wpdb->get_var($qry);
	  if($send_email==1)
	  {
	  $qry="SELECT crf_welcome_email_subject FROM $crf_forms WHERE id=".$content['id'];//Fetches Auto Responder Subject from dashboard settings
	  $subject = $wpdb->get_var($qry);
		if($subject == "")
		{
		  $subject = get_bloginfo('name');//Auto inserts email Subject if it is not defined in dashboard settings
		}
		
	  $qry1="SELECT crf_welcome_email_message FROM $crf_forms WHERE id=".$content['id'];//Fetches registration email body from dashboard settings
	  $message = $wpdb->get_var($qry1);
	  if($message == "" && $form_type=='reg_form')
	  {
		  $message = __('Thank you for registration.',$textdomain);//Auto inserts this text as email body if it is not defined in dashboard settings
	  }
	  
	  if($message == "" && $form_type=='contact_form')
	  {
		  $message = __('Thank you for your submission.',$textdomain);//Auto inserts this text as email body if it is not defined in dashboard settings
	  }
	  
	  if($pwd_show != "no" && $form_type=='reg_form' && $autoapproval=='yes')//Inserts password into registration email body if auto-generation of password is enabled
	  {
		$message .= __('You can use following details for login.',$textdomain);
		$message .= __('Username : ',$textdomain).$user_name;
		$message .= __('password : ',$textdomain).$random_password;
	  }

	  if($form_type=='contact_form')
	  {
		$qry1 = "select * from $crf_fields where Form_Id= '".$content['id']."' and Type ='email' order by ordering asc limit 1";
		 $row1 = $wpdb->get_row($qry1);
		 $emailfield = str_replace(" ","_",$row1->Name);
		 $user_email =  $_POST[$emailfield];  
	  }
	  $headers = 'From:'.$from_email_address. "\r\n"; 
	  wp_mail( $user_email, $subject, $message, $headers );//Sends email to user on successful registration
	  }
	  /*admin notification start */
	  $qry="select `value` from $crf_option where fieldname='adminnotification'";
	  $admin_notification = $wpdb->get_var($qry);
	  if($admin_notification=='yes')
	  {
		$qry="select `value` from $crf_option where fieldname='adminemail'";
	  	$admin_email = $wpdb->get_var($qry); 
		$notification_message = "";
		if(!empty($entry))
		{
			$notification_message .= '<html><body><table cellpadding="10">';
			foreach($entry as $key => $val) 
			{
				if(is_array($val))
				{
					$val = implode(',',$val);	
				}
				$entryval = str_replace("_"," ",$key);
				if($key!="user_pass"):
				
			  $notification_message .= '<tr><td><strong>'.$entryval.'</strong>: </td><td>'.$val.'</td></tr>';
				endif;
			}
			$notification_message .= '</table></body></html>';
		}
		
			/*$headers = "From: " . $user_email . "\r\n";
			$headers .= "Reply-To: ".$user_email. "\r\n";*/
			$headers2 = 'From:'.$from_email_address. "\r\n"; 
			$headers2 .= "MIME-Version: 1.0\r\n";
			$headers2 .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
		
		wp_mail( $admin_email,$form_name.' New Submission Notification', $notification_message,$headers2 );//Sends email to user on successful registration
		 
	  }
	  /*admin notification end */
}
else
{
?>
<?php if($form_type=='reg_form'): ?>
<script type="text/javascript">
    function validateRegister() //Validation for registration form fields
        {
            var user_name = document.getElementById("user_name").value;
            var inputPassword = document.getElementById("inputPassword").value;
            var user_confirm_password = document.getElementById("user_confirm_password").value;
            var user_email = document.getElementById('user_email').value;
			<?php if($enable_captcha=='yes'):?>
            var recaptcha_response_field = document.getElementById('recaptcha_response_field').value;
			<?php endif; ?>
            if (user_name == null || user_name == "") {
                document.getElementById('divuser_name').style.display = 'block';
                document.getElementById("user_name").focus();
                return false;
            } else if (user_email == null || user_email == "") {
                document.getElementById('divuser_email').style.display = 'block';
                document.getElementById('divuser_name').style.display = 'none';
                document.getElementById("user_email").focus();
                return false;
            } else if (!(/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(user_email))) {
                document.getElementById('divuser_email').innerHTML = "<?php _e('Please enter a proper e-mail address.',$textdomain);?>";
                document.getElementById('divuser_email').style.display = 'block';
                document.getElementById('divuser_name').style.display = 'none';
                document.getElementById("user_email").focus();
                return false;
            } else if (inputPassword == null || inputPassword == "") {
                document.getElementById('divinputPassword').style.display = 'block';
                document.getElementById('divinputPassword').style.margin = '-27px 0 23px !important';
                document.getElementById('divuser_name').style.display = 'none';
                document.getElementById('divuser_email').style.display = 'none';
                document.getElementById("inputPassword").focus();
                return false;
            } else if (user_confirm_password == null || user_confirm_password == "") {
                document.getElementById('divuser_confirm_password').style.display = 'block';
                document.getElementById('divuser_confirm_password').style.margin = '-27px 0 23px !important';
                document.getElementById('divinputPassword').style.display = 'none';
                document.getElementById('divuser_name').style.display = 'none';
                document.getElementById('divuser_email').style.display = 'none';
                document.getElementById("user_confirm_password").focus();
                return false;
            } else if (inputPassword != user_confirm_password) {
                with(document.getElementById('divuser_confirm_password')) {
                    innerHTML = "<?php _e('Password and confirm password do not match.',$textdomain);?>";
                    with(style) {
                        display = 'block';
                    }
                }
                document.getElementById('divinputPassword').style.display = 'none';
                document.getElementById('divuser_name').style.display = 'none';
                document.getElementById('divuser_email').style.display = 'none';
                document.getElementById("user_confirm_password").focus();
                return false;
            } 
			<?php if($enable_captcha=='yes'):?>
			else if (recaptcha_response_field == null || recaptcha_response_field == "") {
                with(document.getElementById('divrecaptcha_response_field')) {
                    style.display = 'block';
                    style.width = '299px';
                    style.marginLeft = '170px';
                }
				
                document.getElementById('divuser_confirm_password').style.display = 'none';
                document.getElementById('divinputPassword').style.display = 'none';
                document.getElementById('divuser_name').style.display = 'none';
                document.getElementById('divuser_email').style.display = 'none';
                document.getElementById("recaptcha_response_field").focus();
                return false;
            } 
			<?php endif; ?>
			else {
                return true;
            }
        }
</script>
<?php endif; ?>
<!--HTML for displaying registration form-->
<div id="crf-form">
  <form enctype="multipart/form-data" method="post" action="" id="crf_contact_form" name="crf_contact_form">
    <div class="info-text"><?php echo $custom_text;?></div>
    <div class="crf_contact_form">
      <?php if($form_type=='reg_form'): ?>
      <div class="formtable">
        <div class="crf_label">
          <label for="user_login"><?php _e('Username',$textdomain);?><br>
          </label>
        </div>
        <div class="crf_input">
          <input type="text" size="20" onblur="javascript:validete_userName();" onkeyup="javascript:validete_userName();" onfocus="javascript:validete_userName();" onchange="javascript:validete_userName();" value="<?php echo (!empty($_POST['user_name']))?  $_POST['user_name']: ''; ?>" class="input" id="user_name" name="user_name" required>
          <div class="crf_error_text" id="nameErr"></div>
          <div class="crf_error_text reg_frontErr" id="divuser_name" style="display:none;"><?php _e('Please enter a username.',$textdomain);?></div>
        </div>
      </div>
      <div class="formtable">
        <div class="crf_label">
          <label for="user_email"><?php _e('E-mail',$textdomain);?><br>
          </label>
        </div>
        <div class="crf_input">
          <input type="text" onblur="javascript:validete_email();" onkeyup="javascript:validete_email();" onfocus="javascript:validete_email();" onchange="" size="25" value="<?php echo (!empty($_POST['user_email']))?  $_POST['user_email']: ''; ?>" class="input" id="user_email" name="user_email" required>
          <div class="crf_error_text" id="emailErr"></div>
          <div class="reg_frontErr crf_error_text" id="divuser_email" style="display:none;"><?php _e('Please enter an email address.',$textdomain);?></div>
        </div>
      </div>
      <?php
if($pwd_show == "no")//Shows password field if the user is allowed to chose password during registration
{
?>
      <div class="formtable">
        <div class="crf_label">
          <label for="user_password"><?php _e('Password',$textdomain);?><br>
          </label>
        </div>
        <div class="crf_input">
          <input id="inputPassword" name="inputPassword" type="password" onfocus="javascript:document.getElementById('user_confirm_password').value = '';" />
          <div id="complexity" class="default" style="display:none;"></div>
          <div id="password_info" class="password-pro"><?php _e('At least 7 characters please!',$textdomain);?></div>
          <div class="reg_frontErr crf_error_text" id="divinputPassword" style="display:none;"><?php _e('Please enter a password.',$textdomain);?></div>
        </div>
      </div>
      <div class="formtable">
        <div class="crf_label">
          <label for="user_confirm_password"><?php _e('Confirm Password',$textdomain);?><br>
          </label>
        </div>
        <div class="crf_input">
          <input id="user_confirm_password" name="user_confirm_password" type="password"/>
          <div class="reg_frontErr crf_error_text" id="divuser_confirm_password" style="display:none;"><?php _e('Please enter a confirm password',$textdomain);?></div>
        </div>
      </div>
      <?php
}
else//If auto password generation is enabled then this will create a random password
{
	$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
?>
      <div class="formtable" style="display:none;">
        <div class="crf_label">
          <label for="user_password"><?php _e('Password',$textdomain);?><br>
          </label>
        </div>
        <div class="crf_input">
          <input id="inputPassword" name="inputPassword" type="hidden" value="<?php echo $random_password; ?>" />
        </div>
        <div id="complexity" class="default" style="display:none;"></div>
        <div id="password_info" class="password-pro"><?php _e('At least 7 characters please!',$textdomain);?></div>
        <div class="reg_frontErr crf_error_text" id="divinputPassword" style="display:none;margin: -27px 0 23px !important; margin-left: 258px !important;"><?php _e('Please enter a password.',$textdomain);?></div>
      </div>
      <div class="formtable" style="display:none;">
        <div class="crf_label">
          <label for="user_confirm_password"><?php _e('Confirm Password',$textdomain);?><br>
          </label>
        </div>
        <div class="crf_input">
          <input id="user_confirm_password" name="user_confirm_password" value="<?php echo $random_password; ?>" type="hidden"/>
          <div class="reg_frontErr crf_error_text" id="divuser_confirm_password" style="display:none;margin: -27px 0 23px !important; "><?php _e('Please enter a confirm password.',$textdomain);?></div>
        </div>
      </div>
      <?php
}
?>
     <?php endif;  ?>      
      <!-- HTML for displaying custom fields in Registration form -->
      
        <?php 
$qry1 = "select * from $crf_fields where Form_Id = '".$content['id']."' order by ordering asc";
$reg1 = $wpdb->get_results($qry1);
	 foreach($reg1 as $row1)
	 {
		 $key = str_replace(" ","_",$row1->Name);
		 $value = $row1->Value;
		 if($row1->Type=='heading')
		 {?>
        <div class="formtable crf_heading">
          <h1 name="<?php echo $key;?>" class="<?php echo $row1->Class;?>"><?php echo $row1->Value;?></h1>
        </div>
        <?php 
		}
		if($row1->Type=='paragraph')
		 {?>
        <div class="formtable crf_paragraph">
          <p name="<?php echo $key;?>" class="<?php echo $row1->Class;?>"><?php echo $row1->Option_Value;?></p>
        </div>
        <?php }
if($row1->Type=='term_checkbox')
		 {?>
        <div class="formtable">
          <div class="crf_label">
            <input type="checkbox" value="<?php echo 'yes';?>" id="<?php echo $key;?>" name="<?php echo $key;?>"  class="regular-text <?php echo $row1->Class;?>" <?php if($row1->Require==1)echo 'required';?>>
          
            <label for="<?php echo $key;?>"><?php echo $row1->Description;?></label>
            <div class="reg_frontErr crf_error_text" style="display:none;"></div>
          </div>
        </div>
        <?php }
		if($row1->Type=='DatePicker')
		 {?>
        <div class="formtable">
          <div class="crf_label">
            <label for="<?php echo $key;?>"><?php echo $row1->Name;?></label>
          </div>
          <div class="crf_input">
            <input type="text" class="MyDate regular-text <?php echo $row1->Class;?>" maxlength="<?php echo $row1->Max_Length;?>" value="<?php echo $value;?>" id="<?php echo $key;?>" name="<?php echo $key;?>" <?php if($row1->Readonly==1)echo 'readonly';?> <?php if($row1->Require==1)echo 'required';?>>
          </div>
        </div>
        <?php }
		 if($row1->Type=='text')
		 {?>
        <div class="formtable">
          <div class="crf_label">
            <label for="<?php echo $key;?>"><?php echo $row1->Name;?></label>
          </div>
          <div class="crf_input">
            <input type="text" class="regular-text <?php echo $row1->Class;?>" maxlength="<?php echo $row1->Max_Length;?>" value="<?php echo $value;?>" id="<?php echo $key;?>" name="<?php echo $key;?>" <?php if($row1->Readonly==1)echo 'readonly';?> <?php if($row1->Require==1)echo 'required';?>>
          </div>
        </div>
        <?php }
		if($row1->Type=='email')
		 {?>
        <div class="formtable">
          <div class="crf_label">
            <label for="<?php echo $key;?>"><?php echo $row1->Name;?></label>
          </div>
          <div class="crf_input crf_email">
            <input type="text" class="regular-text <?php echo $row1->Class;?>" maxlength="<?php echo $row1->Max_Length;?>" value="<?php echo $value;?>" id="<?php echo $key;?>" name="<?php echo $key;?>" <?php if($row1->Readonly==1)echo 'readonly';?> <?php if($row1->Require==1)echo 'required';?>>
            <div class="reg_frontErr custom_error crf_error_text" style="display:none;"></div>
          </div>
        </div>
        <?php }
		if($row1->Type=='number')
		 {?>
        <div class="formtable">
          <div class="crf_label">
            <label for="<?php echo $key;?>"><?php echo $row1->Name;?></label>
          </div>
          <div class="crf_input crf_number">
            <input type="text" class="crf_number regular-text <?php echo $row1->Class;?>" maxlength="<?php echo $row1->Max_Length;?>" value="<?php echo $value;?>" id="<?php echo $key;?>" name="<?php echo $key;?>" <?php if($row1->Readonly==1)echo 'readonly';?> <?php if($row1->Require==1)echo 'required';?>>
            <div class="reg_frontErr custom_error crf_error_text" style="display:none;"></div>
          </div>
        </div>
        <?php }

		if($row1->Type=='textarea')
		{?>
        <div class="formtable">
          <div class="crf_label">
            <label for="<?php echo $key;?>"><?php echo $row1->Name;?></label>
          </div>
          <div class="crf_input">
            <textarea  class="regular-text <?php echo $row1->Class;?>" maxlength="<?php echo $row1->Max_Length;?>" cols="<?php echo $row1->Cols;  ?>" rows="<?php echo $row1->Rows;  ?>" id="<?php echo $key;?>" name="<?php echo $key;?>" <?php if($row1->Readonly==1)echo 'readonly';?> <?php if($row1->Require==1)echo 'required';?>><?php echo $value; ?></textarea>
          </div>
        </div>
        <?php }
		if($row1->Type=='radio')
		{
			 $array_value = explode(',',$value);
			?>
        <div class="formtable">
          <div class="crf_label">
            <label for="<?php echo $key;?>"><?php echo $row1->Name;?></label>
          </div>
          <div class="crf_input">
            <?php 
									$arr_radio = explode(',',$row1->Option_Value);
									foreach($arr_radio as $radio)
									{?>
            <label><?php echo $radio; ?></label>
            <input type="radio" class="regular-text  <?php echo $row1->Class;?>" value="<?php echo $radio;?>" <?php if($value!=""){if(in_array($radio,$array_value))echo 'checked';} ?> id="<?php echo $key;?>" style="width:50px;" name="<?php echo $key;?>"  <?php if($row1->Readonly==1)echo 'disabled';?>>
            <?php } ?>
          </div>
        </div>
        <?php }

		if($row1->Type=='checkbox')
	   {
		   $array_value = explode(',',$value);
		   ?>
        <div class="formtable">
          <div class="crf_label">
            <label for="<?php echo $key;?>"><?php echo $row1->Name; ?></label>
          </div>
          <div class="crf_checkbox">
            <?php 
			$arr_radio = explode(',',$row1->Option_Value);
			$radio_count = 1;
			foreach($arr_radio as $radio)
			{?>
            <label><?php echo $radio; ?></label>
            <input type="checkbox" class="regular-text <?php echo $row1->Class;?>" value="<?php echo $radio;?>" id="<?php echo $key;?>"  name="<?php echo $row1->Name.'[]';?>" <?php if($value!=""){if(in_array($radio,$array_value))echo 'checked';} ?> <?php if($row1->Readonly==1)echo 'disabled';?>>
            <?php $radio_count++; 
			} ?>
          </div>
        </div>
        <?php }
		
	   if($row1->Type=='select')
	   {?>
        <div class="formtable">
          <div class="crf_label">
            <label for="<?php echo $key;?>"><?php echo $row1->Name;?></label>
          </div>
          <div class="crf_input crf_select">
            <select class="regular-text <?php echo $row1->Class;?>" id="<?php echo $key;?>" name="<?php echo $key;?>" <?php if($row1->Readonly==1)echo 'disabled';?> <?php if($row1->Require==1) echo 'required';?>>
              <?php
			  $arr = explode(',',$row1->Option_Value);
			  foreach($arr as $ar)
			  {
				  ?>
              <option value="<?php echo $ar;?>" <?php if($ar==$value)echo 'selected';?>><?php echo $ar;?></option>
              <?php	
			  }
			  ?>
            </select>
          </div>
        </div>
        <?php }
		/*file addon start */
		if ( is_plugin_active('file-upload-addon/file-upload.php') && $row1->Type=='file' ) 
		{
			global $fileuploadfunctionality;
			$fileuploadfunctionality = new fileuploadfuncitonality();
			$fileuploadfunctionality->fileuploadhtml($row1,$key);
		}
		/*file addon end */
		 }
		 ?>
      
      <!-- Custom fields in Registration form ends -->
      <?php if($enable_captcha=='yes') : ?>
      <div class="formtable" align="center"><div class="crf_input crf_input_captcha"> <?php echo recaptcha_get_html($publickey, $error); ?> </div></div>
      <div class="reg_frontErr" id="divrecaptcha_response_field" style="display:none;width: 299px !important; margin-left: 170px !important;"> <?php _e('Please fill this to prove you aren\'t a robot.',$textdomain);?> </div>
      <?php endif; ?>
      <br class="clear">
    </div>
    <div class="customcrferror crf_error_text" style="display:none"></div>
    <div class="UltimatePB-Button-area crf_input crf_input_submit" >
      <input type="submit" value="Submit" class="crf_contact_submit" id="submit" name="submit"  <?php if($form_type=='reg_form'): ?>  onClick="return validateRegister()" <?php endif; ?> >
     
    </div>
  </form>
</div>
<?php
	}	
?>
<?php if($form_type=='reg_form'): ?>
<script language="javascript" type="text/javascript">
    //AJAX username validation
    var name = false;
    var email = false;

    function validete_userName() {
        jQuery.ajax({
            type: "POST",
            url: '<?php echo get_option('siteurl').'/wp-admin/admin-ajax.php';?>?action=crf_ajaxcalls&cookie=encodeURIComponent(document.cookie)&function=validateUser&name=' + jQuery("#user_name").val(),
            success: function (serverResponse) {
                if (serverResponse == "true") {
                    jQuery("#nameErr").html("<?php _e('Sorry, username already exist',$textdomain);?>");
                    jQuery("#nameErr").addClass("reg_frontErr");
                    jQuery("#submit").attr('disabled', true);
                } else {
                    jQuery("#nameErr").html('');
                    jQuery("#nameErr").removeClass("reg_frontErr");
                    jQuery("#submit").attr('disabled', false);
                }
            }
        })
    }

    function validete_email() //AJAX email validation
        {
            jQuery.ajax({
                type: "POST",
                url: '<?php echo get_option('siteurl').'/wp-admin/admin-ajax.php';?>?action=crf_ajaxcalls&cookie=encodeURIComponent(document.cookie)&function=validateEmail&email=' + jQuery("#user_email").val(),
                success: function (serverResponse) {
                    if (serverResponse == "true") {
                        email = false;
                        jQuery("#emailErr").html("<?php _e('Sorry, email already exist',$textdomain);?>");
                        jQuery("#emailErr").addClass("reg_frontErr");
                        jQuery("#submit").attr('disabled', true);
                    } else {
                        jQuery("#emailErr").html('');
                        jQuery("#submit").attr('disabled', false);
                        jQuery("#emailErr").removeClass("reg_frontErr");
                    }
                }
            })
        }
</script>
<?php endif; ?>
<script>
    jQuery(document).ready(function () {
        //for date picker
        jQuery('.MyDate').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    });
    jQuery('#crf_contact_form').submit(function () {
        //email validation start for custom field	
        var email_val = "";
        var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        jQuery('.custom_error').html('');
        jQuery('.custom_error').hide();
        jQuery('.customcrferror').html('');
        jQuery('.crf_email').each(function (index, element) {
            var email = jQuery(this).children('input').val();
            var isemail = regex.test(email);
            if (isemail == false && email != "") {
                jQuery(this).children('.custom_error').html('<?php _e('Please enter a valid e-mail address.',$textdomain);?>');
                jQuery(this).children('.custom_error').show();
            }
        });
		/*file addon start */
		 jQuery('.crf_file').each(function (index, element) {
			var val = jQuery(this).children('input').val().toLowerCase();
			var allowextensions = jQuery(this).children('input').attr('data-filter-placeholder');
			if(allowextensions=='')
			{
				allowextensions = '<?php echo get_option('ucf_allowfiletypes','jpg|jpeg|png|gif|doc|pdf|docx|txt|psd'); ?>';
			}
			var regex = new RegExp("(.*?)\.(" + allowextensions + ")$");
			if(!(regex.test(val)) && val!="") {
			
				jQuery(this).children('.custom_error').html('<?php _e('This file type is not allowed.',$textdomain);?>');
                jQuery(this).children('.custom_error').show();
			}
        });
		/*file addon end */
        jQuery('.crf_number').each(function (index, element) { //Validation for number type custom field
            var number = jQuery(this).children('input').val();
            var isnumber = jQuery.isNumeric(number);
            if (isnumber == false && number != "") {
                jQuery(this).children('.custom_error').html('<?php _e('Please enter a valid number',$textdomain);?>');
                jQuery(this).children('.custom_error').show();
            }
        });
        var b = '';
        b = jQuery('.custom_error').each(function () {
            var a = jQuery(this).html();
            b = a + b;
            jQuery('.customcrferror').html(b);
        });
        var error = jQuery('.customcrferror').html();
        if (error == '') {
            return true;
        } else {
            return false;
        }
    });
jQuery('.input-box').addClass('crf_input');
jQuery('.lable-text').addClass('crf_label');

</script> 