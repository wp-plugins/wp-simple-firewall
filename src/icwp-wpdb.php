<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * Version: 2013-08-14_A
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

if ( !class_exists('ICWP_WpDb_V1') ):

	abstract class ICWP_WpDb_V1 {

		/**
		 * @var ICWP_WpDb_V1
		 */
		protected static $oInstance = NULL;

		/**
		 * @var wpdb
		 */
		protected static $oWpdb;

		/**
		 * @return ICWP_WpFunctions_V5
		 */
		abstract public static function GetInstance();

		public function __construct() {}

		/**
		 * @param $sSql
		 *
		 * @return null|mixed
		 */
		public function getVar( $sSql ) {
			return $this->loadWpdb()->get_var( $sSql );
		}

		/**
		 * Given any SQL query, will perform it using the WordPress database object.
		 *
		 * @param string $sSqlQuery
		 * @return integer|boolean (number of rows affected or just true/false)
		 */
		public function doSql( $sSqlQuery ) {
			$mResult = $this->loadWpdb()->query( $sSqlQuery );
			return $mResult;
		}

		/**
		 * @return string
		 */
		public function getTable_Comments() {
			$oDb = $this->loadWpdb();
			return $oDb->comments;
		}

		/**
		 * Loads our WPDB object if required.
		 *
		 * @return wpdb
		 */
		protected function loadWpdb() {
			if ( is_null( $this->oWpdb ) ) {
				$this->oWpdb = $this->getWpdb();
			}
			return $this->oWpdb;
		}

		/**
		 */
		private function getWpdb() {
			global $wpdb;
			return $wpdb;
		}
	}
endif;

if ( !class_exists('ICWP_WPSF_WpDb') ):

	class ICWP_WPSF_WpDb extends ICWP_WpDb_V1 {
		/**
		 * @return ICWP_WPSF_WpDb
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}
	}
endif;