<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 * 
 * Version: 2013-12-17-V1
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

if ( !class_exists('ICWP_Once_V1') ):

class ICWP_Once_V1 {
	/**
	 * @var ICWP_WPSF_Once
	 */
	protected static $oInstance = NULL;
	
	/**
	 * @return ICWP_Once_V1
	 */
	public static function & GetInstance( $insCalledClass = '' ) {
		if ( is_null( self::$oInstance ) ) {
			if ( function_exists( 'get_called_class' ) ) {
				$sCalledClass = get_called_class();
				self::$oInstance = new $sCalledClass();
			}
			else {
				self::$oInstance = new $insCalledClass();
			}
		}
		return self::$oInstance;
	}
}

endif;

if ( !class_exists('ICWP_WPSF_Once') ):
	class ICWP_WPSF_Once extends ICWP_Once_V1 { }
endif;