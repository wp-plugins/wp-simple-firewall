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

if ( !class_exists('ICWP_WPSF_Processor_AdminAccessRestriction') ):

class ICWP_WPSF_Processor_AdminAccessRestriction extends ICWP_WPSF_BaseProcessor {

	/**
	 * @var string
	 */
	protected $sOptionRegexPattern;

	/**
	 * @param ICWP_WPSF_FeatureHandler_AdminAccessRestriction  $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions );
	}

	public function run() {

		if ( ! $this->oFeatureOptions->getIsUpgrading() ) {
			$this->sOptionRegexPattern = '/^'. $this->oFeatureOptions->getOptionStoragePrefix() . '.*_options$/';
			add_filter( 'pre_update_option', array( $this, 'blockOptionsSaves' ), 1, 3 );
		}

	}

	/**
	 * Right before a plugin option is due to update it will check that we have permissions to do so and if not, will
	 * revert the option to save to the previous one.
	 *
	 * @param $mValue
	 * @param $sOption
	 * @param $mOldValue
	 * @return mixed
	 */
	public function blockOptionsSaves( $mValue, $sOption, $mOldValue ) {
		if ( !preg_match( $this->sOptionRegexPattern, $sOption ) ) {
			return $mValue;
		}
		return apply_filters( $this->doPluginPrefix( 'has_permission_to_submit' ), true )? $mValue : $mOldValue;
	}
}

endif;
