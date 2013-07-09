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

if ( !class_exists('ICWP_DataProcessor') ):

class ICWP_DataProcessor {
	
	static public function ExtractIpAddresses( $insAddresses = '' ) {
		
		$aAddresses = array();
		
		if ( empty( $insAddresses ) ) {
			return $aAddresses;
		}
		$aRawList = array_map( 'trim', explode( "\n", $insAddresses ) );
		
		foreach( $aRawList as $sKey => $sRawAddress ) {
			$aRawList[ $sKey ] = self::Clean_Ip( $sRawAddress );;
		}
		
		if ( function_exists('filter_var') && defined( FILTER_VALIDATE_IP )  ) {
			$fUseFilter = true;
		}
		else {
			$fUseFilter = false;
		}
		
		foreach( $aRawList as $sAddress ) {
			if ( self::Verify_Ip( $sAddress, $fUseFilter ) ) {
				$aAddresses[] = $sAddress;
			}
		}
		
		return array_unique( $aAddresses );
	}
	
	public static function Clean_Ip( $insRawAddress ) {
		$insRawAddress = preg_replace( '/[a-z\s]/i', '', $insRawAddress );
		$insRawAddress = str_replace( '.', 'PERIOD', $insRawAddress );
		$insRawAddress = preg_replace( '/[^a-z0-9]/i', '', $insRawAddress );
		$insRawAddress = str_replace( 'PERIOD', '.', $insRawAddress );
		return $insRawAddress;
	}
	
	public static function Verify_Ip( $insIpAddress, $infUseFilter = false ) {
		if ( $infUseFilter ) {
			if ( filter_var( $insIpAddress, FILTER_VALIDATE_IP ) ) {
				return true;
			}
		}
		else {
			if ( preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/', $insIpAddress ) ) { //It's a valid IPv4 format, now check components
				$aParts = explode( '.', $insIpAddress );
				foreach ( $aParts as $sPart ) {
					$sPart = intval( $sPart );
					if ( $sPart < 0 || $sPart > 255 ) {
						return false;
					}
				}
				return true;
			}
		}
	}
	
}

endif;