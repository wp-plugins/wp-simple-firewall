<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
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

require_once( dirname(__FILE__).'/icwp-base-processor.php' );

if ( !class_exists('ICWP_EmailProcessor_V1') ):

class ICWP_EmailProcessor_V1 extends ICWP_WPSF_BaseProcessor {

	const Slug = 'email';
	
	protected $m_sRecipientAddress;
	protected $m_sSiteName;

	/**
	 * @var string
	 */
	static protected $sModeFile_EmailThrottled;
	/**
	 * @var int
	 */
	static protected $nThrottleInterval = 1; 
	/**
	 * @var int
	 */
	protected $m_nEmailThrottleLimit;
	/**
	 * @var int
	 */
	protected $m_nEmailThrottleTime;
	/**
	 * @var int
	 */
	protected $m_nEmailThrottleCount;
	/**
	 * @var boolean
	 */
	protected $m_fEmailIsThrottled;

	public function __construct( $oPluginVo ) {
		parent::__construct( $oPluginVo, self::Slug );
	}
	
	public function reset() {
		parent::reset();
		self::$sModeFile_EmailThrottled = dirname( __FILE__ ).'/../mode.email_throttled';
	}
	
	/**
	 * @param string $insEmailAddress
	 * @param string $insEmailSubject
	 * @param array $inaMessage
	 * @uses wp_mail
	 */
	public function sendEmailTo( $insEmailAddress, $insEmailSubject, $inaMessage ) {
		
		$aHeaders = array(
			'MIME-Version: 1.0',
			'Content-type: text/plain;',
			sprintf( 'From: %s, Simple Firewall Plugin <%s>', $this->getSiteName(), $insEmailAddress ),
			sprintf( "Subject: %s", $insEmailSubject ),
			'X-Mailer: PHP/'.phpversion()
		);
		
		$this->updateEmailThrottle();
		// We make it appear to have "succeeded" if the throttle is applied.
		if ( $this->m_fEmailIsThrottled ) {
			return true;
		}
		$fSuccess = wp_mail( $insEmailAddress, $insEmailSubject, implode( "\r\n", $inaMessage ), implode( "\r\n", $aHeaders ) );
		$this->store();
		return $fSuccess;
	}
	
	/**
	 * Will send email to the default recipient setup in the object.
	 * 
	 * @param string $insEmailSubject
	 * @param array $inaMessage
	 */
	public function sendEmail( $insEmailSubject, $inaMessage ) {
		return $this->sendEmailTo( $this->getDefaultRecipientAddress(), $insEmailSubject, $inaMessage );
	}
	
	/**
	 * Whether we're throttled is dependent on 2 signals.  The time interval has changed, or the there's a file
	 * system object telling us we're throttled.
	 * 
	 * The file system object takes precedence.
	 * 
	 * @return boolean
	 */
	protected function updateEmailThrottle() {

		// Throttling Is Effectively Off
		if ( $this->getThrottleLimit() <= 0 ) {
			$this->setThrottledFile( false );
			return $this->m_fEmailIsThrottled;
		}
		
		// Check that there is an email throttle file. If it exists and its modified time is greater than the 
		// current $this->m_nEmailThrottleTime it suggests another process has touched the file and updated it
		// concurrently. So, we update our $this->m_nEmailThrottleTime accordingly.
		if ( is_file( self::$sModeFile_EmailThrottled ) ) {
			$nModifiedTime = filemtime( self::$sModeFile_EmailThrottled );
			if ( $nModifiedTime > $this->m_nEmailThrottleTime ) {
				$this->m_nEmailThrottleTime = $nModifiedTime;
			}
		}
		
		$nNow = time();
		if ( !isset($this->m_nEmailThrottleTime) || $this->m_nEmailThrottleTime > $nNow ) {
			$this->m_nEmailThrottleTime = $nNow;
		}
		if ( !isset($this->m_nEmailThrottleCount) ) {
			$this->m_nEmailThrottleCount = 0;
		}
		
		// If $nNow is greater than throttle interval (1s) we turn off the file throttle and reset the count
		$nDiff = $nNow - $this->m_nEmailThrottleTime;
		if ( $nDiff > self::$nThrottleInterval ) {
			$this->m_nEmailThrottleTime = $nNow;
			$this->m_nEmailThrottleCount = 1;	//we set to 1 assuming that this was called because we're about to send, or have just sent, an email.
			$this->setThrottledFile( false );
		}
		else if ( is_file( self::$sModeFile_EmailThrottled ) || ( $this->m_nEmailThrottleCount >= $this->getThrottleLimit() ) ) {
			$this->setThrottledFile( true );
		}
		else {
			$this->m_nEmailThrottleCount++;
		}
	}
	
	public function setThrottledFile( $infOn = false ) {
		
		$this->m_fEmailIsThrottled = $infOn;
		
		if ( $infOn && !is_file( self::$sModeFile_EmailThrottled ) && function_exists('touch') ) {
			@touch( self::$sModeFile_EmailThrottled );
		}
		else if ( !$infOn && is_file(self::$sModeFile_EmailThrottled) ) {
			@unlink( self::$sModeFile_EmailThrottled );
		}
	}
	
	public function setDefaultRecipientAddress( $insEmailAddress ) {
		$this->m_sRecipientAddress = $insEmailAddress;
	}
	
	public function getDefaultRecipientAddress() {
		if ( empty( $this->m_sRecipientAddress ) ) {
			$this->m_sRecipientAddress = $this->m_aOptions[ 'block_send_email_address' ];
		}
		return $this->m_sRecipientAddress;
	}
	
	public function setSiteName( $insName ) {
		$this->m_sSiteName = $insName;
	}
	
	public function getSiteName() {
		if ( empty( $this->m_sSiteName ) ) {
			$this->m_sSiteName = function_exists( 'get_bloginfo' )? get_bloginfo('name') : 'WordPress Site';
		}
		return $this->m_sSiteName;
	}
	
	public function getThrottleLimit() {
		if ( empty( $this->m_nEmailThrottleLimit ) ) {
			$this->m_nEmailThrottleLimit = $this->m_aOptions[ 'send_email_throttle_limit' ];
		}
		return $this->m_nEmailThrottleLimit;
	}
}

endif;

if ( !class_exists('ICWP_WPSF_EmailProcessor') ):
	class ICWP_WPSF_EmailProcessor extends ICWP_EmailProcessor_V1 { }
endif;