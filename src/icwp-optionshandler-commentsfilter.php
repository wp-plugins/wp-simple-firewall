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
	
	public function definePluginOptions() {

		$this->m_aDirectSaveOptions = array( 'enable_comments_filter' );
		
		$aBase = array(
			'section_title' => 'Enable Comments Filter',
			'section_options' => array(
				array(
					'enable_comments_filter',
					'',
					'N',
					'checkbox',
					'Enable Comments Filter',
					'Enable (or Disable) The Comments Filter Feature',
					'Regardless of any other settings, this option will turn Off the Comments Filter feature, or enable your chosen Comments Filter options.'
				)
			),
		);
		$aGasp = array(
			'section_title' => 'G.A.S.P. Comment SPAM Protection',
			'section_options' => array(
				array(
					'enable_comments_gasp_protection',
					'',
					'N',
					'checkbox',
					'GASP Protection',
					'Add Growmap Anti Spambot Protection to your comments',
					'Taking the lead from the original GASP plugin for WordPress, we have extended it to include further protection. '.sprintf( '[%smore info%s]', '<a href="http://icwp.io/2n" target="_blank">', '</a>' )
				),
				array(
					'comments_cooldown_interval',
					'',
					'30',
					'integer',
					'Comments Cooldown',
					'Limit posting a comment to X seconds after the page has loaded',
					"By forcing a comments cooldown period, you restrict a Spambot's ability to post mutliple times to your posts."
				),
				array(
					'comments_token_expire_interval',
					'',
					'600',
					'integer',
					'Comment Token Expire',
					'A visitor has X seconds within which to post a comment',
					"Default: 10 minutes (600 seconds). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors."
				)
			)
		);

		$this->m_aOptions = array(
			$aBase,
			$aGasp
		);
	}

	public function updateHandler() {
		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
	}
}

endif;