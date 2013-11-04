<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once( dirname(__FILE__).'/icwp-optionshandler-base.php' );

if ( !class_exists('ICWP_OptionsHandler_CommentsFilter') ):

class ICWP_OptionsHandler_CommentsFilter extends ICWP_OptionsHandler_Base_WPSF {

	const StoreName = 'commentsfilter_options';
	
	const DefaultCommentCooldown	= 30; //seconds.
	const DefaultCommentExpire		= 600; //seconds.
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	public function defineOptions() {

		$this->m_aDirectSaveOptions = array();
		
		$aBase = array(
			'section_title' => 'Enable Comments Filter',
			'section_options' => array(
				array(
					'enable_comments_filter',
					'',
					'Y',
					'checkbox',
					__( 'Enable Comments Filter', 'wp-simple-firewall' ),
					__( 'Enable (or Disable) The SPAM Comments Filter Feature.', 'wp-simple-firewall' ),
					__( 'Regardless of any other settings, this option will turn off the Comments Filter feature, or enable your chosen Comments Filter options.', 'wp-simple-firewall' )
				)
			),
		);
		$aGasp = array(
			'section_title' => 'G.A.S.P. Comment SPAM Protection',
			'section_options' => array(
				array(
					'enable_comments_gasp_protection',
					'',
					'Y',
					'checkbox',
					__( 'GASP Protection', 'wp-simple-firewall' ),
					__( 'Add Growmap Anti Spambot Protection to your comments', 'wp-simple-firewall' ),
					__( 'Taking the lead from the original GASP plugin for WordPress, we have extended it to include further protection.', 'wp-simple-firewall' )
						.' '.sprintf( __( '%smore info%s', 'wp-simple-firewall' ), '[<a href="http://icwp.io/2n" target="_blank">', '</a>]' )
				),
				array(
					'enable_comments_gasp_protection_for_logged_in',
					'',
					'N',
					'checkbox',
					__( 'Include Logged-In Users', 'wp-simple-firewall' ),
					__( 'You may also enable GASP for logged in users', 'wp-simple-firewall' ),
					__( 'Since logged-in users would be expected to be vetted already, this is off by default.', 'wp-simple-firewall' )
				),
				array(
					'comments_cooldown_interval',
					'',
					'30',
					'integer',
					__( 'Comments Cooldown', 'wp-simple-firewall' ),
					__( 'Limit posting comments to X seconds after the page has loaded', 'wp-simple-firewall' ),
					__( "By forcing a comments cooldown period, you restrict a Spambot's ability to post mutliple times to your posts.", 'wp-simple-firewall' )
				),
				array(
					'comments_token_expire_interval',
					'',
					'600',
					'integer',
					__( 'Comment Token Expire', 'wp-simple-firewall' ),
					__( 'A visitor has X seconds within which to post a comment', 'wp-simple-firewall' ),
					__( "Default: 600 seconds (10 minutes). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors.", 'wp-simple-firewall' )
					
				),
				array(
					'custom_message_checkbox',
					'',
					__( "I'm not a spammer", 'wp-simple-firewall' ),
					'text',
					__( 'Custom Checkbox Message', 'wp-simple-firewall' ),
					__( 'If you want a custom checkbox message, please provide this here', 'wp-simple-firewall' ),
					__( "You can customise the message beside the checkbox.", 'wp-simple-firewall' )
						.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __("Please check the box to confirm you're not a spammer", 'wp-simple-firewall') )
				),
				array(
					'custom_message_alert',
					'',
					__( "Please check the box to confirm you're not a spammer", 'wp-simple-firewall' ),
					'text',
					__( 'Custom Alert Message', 'wp-simple-firewall' ),
					__( 'If you want a custom alert message, please provide this here', 'wp-simple-firewall' ),
					__( "This alert message is displayed when a visitor attempts to submit a comment without checking the box.", 'wp-simple-firewall' )
						.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __("Please check the box to confirm you're not a spammer", 'wp-simple-firewall') )
				),
				array(
					'custom_message_comment_wait',
					'',
					__( "Please wait %s seconds before posting your comment", 'wp-simple-firewall' ),
					'text',
					__( 'Custom Wait Message', 'wp-simple-firewall' ),
					__( 'If you want a custom submit-button wait message, please provide this here.', 'wp-simple-firewall' ),
					__( "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these.", 'wp-simple-firewall' )
						.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __('Please wait %s seconds before posting your comment', 'wp-simple-firewall') )
				),
				array(
					'custom_message_comment_reload',
					'',
					__( "Please reload this page to post a comment", 'wp-simple-firewall' ),
					'text',
					__( 'Custom Reload Message', 'wp-simple-firewall' ),
					__( 'If you want a custom message when the comment token has expired, please provide this here.', 'wp-simple-firewall' ),
					__( 'This message is displayed on the submit-button when the comment token is expired', 'wp-simple-firewall' )
						.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __("Please reload this page to post a comment", 'wp-simple-firewall') )
				)
			)
		);

		$this->m_aOptions = array(
			$aBase,
			$aGasp
		);
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {

		$nCommentCooldown = $this->getOpt( 'comments_cooldown_interval' );
		if ( $nCommentCooldown < 0 ) {
			$nCommentCooldown = 0;
		}
		
		$nCommentTokenExpire = $this->getOpt( 'comments_token_expire_interval' );
		if ( $nCommentTokenExpire < 0 ) {
			$nCommentTokenExpire = 0;
		}
		
		if ( $nCommentTokenExpire != 0 && $nCommentCooldown > $nCommentTokenExpire ) {
			$nCommentCooldown = self::DefaultCommentCooldown;
			$nCommentTokenExpire = self::DefaultCommentExpire;
		}
		$this->setOpt( 'comments_cooldown_interval', $nCommentCooldown );
		$this->setOpt( 'comments_token_expire_interval', $nCommentTokenExpire );
	}
	
	public function updateHandler() {
		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
	}
}

endif;