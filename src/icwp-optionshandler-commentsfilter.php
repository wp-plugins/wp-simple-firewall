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

class ICWP_OptionsHandler_CommentsFilter extends ICWP_OptionsHandler_Base_Wpsf {

	const StoreName = 'commentsfilter_options';
	
	const DefaultCommentCooldown	= 30; //seconds.
	const DefaultCommentExpire		= 600; //seconds.
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	/**
	 * @return void
	 */
	public function setOptionsKeys() {
		if ( !isset( $this->m_aOptionsKeys ) ) {
			$this->m_aOptionsKeys = array(
				'enable_comments_filter',
				'enable_comments_gasp_protection',
				'enable_comments_gasp_protection_for_logged_in',
				'comments_cooldown_interval',
				'comments_token_expire_interval',
				'custom_message_checkbox',
				'custom_message_alert',
				'custom_message_comment_wait',
				'custom_message_comment_reload'
			);
		}
	}
	
	public function defineOptions() {

		$this->m_aDirectSaveOptions = array();
		
		$aBase = array(
			'section_title' => _wpsf__( 'Enable Comments Filter' ),
			'section_options' => array(
				array(
					'enable_comments_filter',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Comments Filter' ),
					_wpsf__( 'Enable (or Disable) The SPAM Comments Filter Feature.' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Comments Filter feature, or enable your chosen Comments Filter options.' ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3z" target="_blank">', '</a>' )
				)
			),
		);
		$aGasp = array(
			'section_title' => _wpsf__( 'G.A.S.P. Comment SPAM Protection' ),
			'section_options' => array(
				array(
					'enable_comments_gasp_protection',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'GASP Protection' ),
					_wpsf__( 'Add Growmap Anti Spambot Protection to your comments' ),
					_wpsf__( 'Taking the lead from the original GASP plugin for WordPress, we have extended it to include further protection.' ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3n" target="_blank">', '</a>' )
						.' | '.sprintf( _wpsf__( '%sblog%s' ), '<a href="http://icwp.io/2n" target="_blank">', '</a>' )
				),
				array(
					'enable_comments_gasp_protection_for_logged_in',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Include Logged-In Users' ),
					_wpsf__( 'You may also enable GASP for logged in users' ),
					_wpsf__( 'Since logged-in users would be expected to be vetted already, this is off by default.' )
				),
				array(
					'comments_cooldown_interval',
					'',
					'30',
					'integer',
					_wpsf__( 'Comments Cooldown' ),
					_wpsf__( 'Limit posting comments to X seconds after the page has loaded' ),
					_wpsf__( "By forcing a comments cooldown period, you restrict a Spambot's ability to post mutliple times to your posts." ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3o" target="_blank">', '</a>' )
				),
				array(
					'comments_token_expire_interval',
					'',
					'600',
					'integer',
					_wpsf__( 'Comment Token Expire' ),
					_wpsf__( 'A visitor has X seconds within which to post a comment' ),
					_wpsf__( "Default: 600 seconds (10 minutes). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors." ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3o" target="_blank">', '</a>' )
					
				),
				array(
					'custom_message_checkbox',
					'',
					_wpsf__( "I'm not a spammer" ),
					'text',
					_wpsf__( 'Custom Checkbox Message' ),
					_wpsf__( 'If you want a custom checkbox message, please provide this here' ),
					_wpsf__( "You can customise the message beside the checkbox." )
						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please check the box to confirm you're not a spammer") ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3p" target="_blank">', '</a>' )
				),
				array(
					'custom_message_alert',
					'',
					_wpsf__( "Please check the box to confirm you're not a spammer" ),
					'text',
					_wpsf__( 'Custom Alert Message' ),
					_wpsf__( 'If you want a custom alert message, please provide this here' ),
					_wpsf__( "This alert message is displayed when a visitor attempts to submit a comment without checking the box." )
						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please check the box to confirm you're not a spammer") ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3p" target="_blank">', '</a>' )
				),
				array(
					'custom_message_comment_wait',
					'',
					_wpsf__( "Please wait %s seconds before posting your comment" ),
					'text',
					_wpsf__( 'Custom Wait Message' ),
					_wpsf__( 'If you want a custom submit-button wait message, please provide this here.' ),
					_wpsf__( "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these." )
						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__('Please wait %s seconds before posting your comment') ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3p" target="_blank">', '</a>' )
				),
				array(
					'custom_message_comment_reload',
					'',
					_wpsf__( "Please reload this page to post a comment" ),
					'text',
					_wpsf__( 'Custom Reload Message' ),
					_wpsf__( 'If you want a custom message when the comment token has expired, please provide this here.' ),
					_wpsf__( 'This message is displayed on the submit-button when the comment token is expired' )
						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please reload this page to post a comment") ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3p" target="_blank">', '</a>' )
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