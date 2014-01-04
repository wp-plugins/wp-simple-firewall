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

require_once( dirname(__FILE__).'/icwp-base-processor.php' );
require_once( dirname(__FILE__).'/icwp-data-processor.php' );

if ( !class_exists('ICWP_ImportBaseProcessor') ):

class ICWP_ImportBaseProcessor extends ICWP_WPSF_BaseProcessor {
	
	/**
	 * The options prefix used by the target plugin
	 * @var string
	 */
	protected $m_sOptionPrefix;
	
	/**
	 * The value of all the options of the source plugin (keys of the options map array)
	 * @var array
	 */
	protected $m_aSourceValues;

	/**
	 * An associative array of option keys from the source plugin mapped to the keys of the target plugin
	 * @var unknown_type
	 */
	protected $m_aOptionsMap;
	
	public function __construct( $sTargetOptionPrefix = '' ) {
		$this->m_sOptionPrefix = $sTargetOptionPrefix;
	}
	
	public function runImport() {
		$this->populateSourceOptions();
		$this->mapOptionsToTarget();
	}
	
	protected function mapOptionsToTarget() { }
	
	/**
	 * Uses the keys from the Options Map array and populates the source value array with the database values.
	 */
	protected function populateSourceOptions() {
		foreach( $this->m_aOptionsMap as $sOptionName => $sTarget ) {
			$this->m_aSourceValues[ $sOptionName ] = get_option( $sOptionName );
		}
	}

	/**
	 * Updates the target plugin options with the values (using the options prefix)
	 * 
	 * @param string $insKey
	 * @param string|mixed $inmValue
	 */
	protected function updateTargetOption( $insKey, $inmValue ) {
		$fResult = update_option( $this->m_sOptionPrefix.$insKey, $inmValue );
	}

	/**
	 * Gets the target plugin option value for the key (using the options prefix)
	 * 
	 * @param string $insKey
	 */
	protected function getTargetOption( $insKey ) {
		return get_option( $this->m_sOptionPrefix.$insKey );
	}
}

endif;