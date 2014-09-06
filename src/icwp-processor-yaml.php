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
 *
 */

if ( !class_exists('ICWP_WPSF_YamlProcessor_V1') ):

	class ICWP_WPSF_YamlProcessor_V1 {

		/**
		 * @var ICWP_WPSF_YamlProcessor_V1
		 */
		protected static $oInstance = NULL;

		/**
		 * @param string $sYamlString
		 * @return array
		 */
		public function parseYamlString( $sYamlString ) {
			$this->loadYamlParser();
			return Spyc::YAMLLoadString( $sYamlString );
		}

		/**
		 *
		 */
		public function loadYamlParser() {
			if ( !class_exists( 'Spyc' ) ) {
				require_once( 'lib/yaml/Spyc.php' );
			}
		}
	}
endif;

if ( !class_exists('ICWP_WPSF_YamlProcessor') ):

	class ICWP_WPSF_YamlProcessor extends ICWP_WPSF_YamlProcessor_V1 {
		/**
		 * @return ICWP_WPSF_YamlProcessor
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}
	}
endif;