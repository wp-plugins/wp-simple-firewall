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
 *
 */

if ( !class_exists('ICWP_BaseProcessor') ):

class ICWP_BaseProcessor {
	
	const PcreDelimiter = '/';
	const LOG_MESSAGE_TYPE_INFO = 0;
	const LOG_MESSAGE_TYPE_WARNING = 1;
	const LOG_MESSAGE_TYPE_CRITICAL = 2;

	protected $m_aLog;
	protected $m_aLogMessages;

	public function __construct() {	}
	
	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		$this->resetLog();
	}
	
	/**
	 * Resets the log
	 */
	public function resetLog() {
		$this->m_aLogMessages = array();
	}
	
	/**
	 * Builds and returns the full log.
	 * 
	 * @return array (associative)
	 */
	public function getLogData() {
		$this->m_aLog = array(
			'messages'			=> serialize( $this->m_aLogMessages ),
		);
		return $this->m_aLog;
	}
	
	/**
	 * @param string $insLogMessage
	 * @param string $insMessageType
	 */
	public function writeLog( $insLogMessage = '', $insMessageType = self::LOG_MESSAGE_TYPE_INFO ) {
		if ( !is_array( $this->m_aLogMessages ) ) {
			$this->resetLog();
		}
		$this->m_aLogMessages[] = array( $insMessageType, $insLogMessage );
	}
	/**
	 * @param string $insLogMessage
	 */
	public function logInfo( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_TYPE_INFO );
	}
	/**
	 * @param string $insLogMessage
	 */
	public function logWarning( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_TYPE_WARNING );
	}
	/**
	 * @param string $insLogMessage
	 */
	public function logCritical( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_TYPE_CRITICAL );
	}

	/**
	 * Cloudflare compatible.
	 * 
	 * @return number - visitor IP Address as IP2Long
	 */
	public static function GetVisitorIpAddress( $infAsLong = true ) {
	
		$sIpAddress = empty($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["REMOTE_ADDR"] : $_SERVER["HTTP_X_FORWARDED_FOR"];
	
		if( strpos($sIpAddress, ',') !== false ) {
			$sIpAddress = explode(',', $sIpAddress);
			$sIpAddress = $sIpAddress[0];
		}
	
		return $infAsLong? ip2long( $sIpAddress ) : $sIpAddress;
	
	}

	/**
	 * @param string $insEmailAddress
	 * @param string $insEmailSubject
	 * @param array $inaMessage
	 */
	public function sendEmail( $insEmailAddress, $insEmailSubject, $inaMessage ) {
	
		require_once( ABSPATH . 'wp-includes/pluggable.php' );
		
		$sSiteName = ( function_exists('get_bloginfo') )? get_bloginfo('name') : '';
		$aHeaders   = array(
			'MIME-Version: 1.0',
			'Content-type: text/plain;',
			"Reply-To: Site Admin <$insEmailAddress>",
			'Subject: '.$insEmailSubject,
			'X-Mailer: PHP/'.phpversion()
		);
		wp_mail( $insEmailAddress, $insEmailSubject, implode( "\r\n", $inaMessage ), implode( "\r\n", $aHeaders ) );
	}
	
}

endif;