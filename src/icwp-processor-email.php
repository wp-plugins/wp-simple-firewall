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

require_once( dirname(__FILE__).'/icwp-base-processor.php' );

if ( !class_exists('ICWP_EmailProcessor') ):

class ICWP_EmailProcessor extends ICWP_BaseProcessor_WPSF {
	
	protected $m_sRecipientAddress;
	protected $m_sSiteName;

	/**
	 * @var string
	 */
	static protected $sModeFile_EmailThrottled;
	/**
	 * @var int
	 */
	static protected $nThrottleInterval = 20; 
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
			sprintf( 'From: %s, Simple Firewall Plugin <%s>', $this->m_sSiteName, $insEmailAddress ),
			sprintf( "Subject: %s", $insEmailSubject ),
			'X-Mailer: PHP/'.phpversion()
		);
		
		$this->updateEmailThrottle();
		// We appear to have "succeeded" if the throttle is applied.
		if ( $this->m_fEmailIsThrottled ) {
			return true;
		}
		return wp_mail( $insEmailAddress, $insEmailSubject, implode( "\r\n", $inaMessage ), implode( "\r\n", $aHeaders ) );
	}
	
	/**
	 * Will send email to the default recipient setup in the object.
	 * 
	 * @param string $insEmailSubject
	 * @param array $inaMessage
	 */
	public function sendEmail( $insEmailSubject, $inaMessage ) {
		if ( !isset( $this->m_sRecipientAddress ) ) {
			return false;
		}
		return $this->sendEmailTo( $this->m_sRecipientAddress, $insEmailSubject, $inaMessage );
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
		if ( $this->m_nEmailThrottleLimit <= 0 ) {
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
		else if ( is_file( self::$sModeFile_EmailThrottled ) || ( $this->m_nEmailThrottleCount >= $this->m_nEmailThrottleLimit ) ) {
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
		else if ( is_file(self::$sModeFile_EmailThrottled) ) {
			@unlink( self::$sModeFile_EmailThrottled );
		}
	}
	
	public function setDefaultRecipientAddress( $insEmailAddress ) {
		$this->m_sRecipientAddress = $insEmailAddress;
	}
	
	public function setSiteName( $insName ) {
		$this->m_sSiteName = $insName;
	}
	
	public function setThrottleLimit( $innLimit ) {
		$this->m_nEmailThrottleLimit = $innLimit;
	}
}

endif;