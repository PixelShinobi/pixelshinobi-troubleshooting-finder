<?php
/**
 * The verdict of a bisect search.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Bisect;

/**
 * Value object for what a {@see BisectSession} concluded.
 *
 * The type strings are an API: Milestone 2 stores them in a WordPress
 * option, so renaming one is a breaking change (see CLAUDE.md).
 */
final class BisectConclusion {

	public const CULPRIT_FOUND         = 'culprit-found';
	public const NOT_A_CANDIDATE       = 'not-a-candidate';
	public const NOT_REPRODUCIBLE      = 'not-reproducible';
	public const INTERACTION_SUSPECTED = 'interaction-suspected';
	public const NO_CANDIDATES         = 'no-candidates';

	/**
	 * One of the class constants.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The guilty plugin ID; only set for CULPRIT_FOUND.
	 *
	 * @var string|null
	 */
	private $culprit;

	/**
	 * The smallest set of plugins that was seen producing the fault.
	 * For INTERACTION_SUSPECTED this is where to look for the combination.
	 *
	 * @var string[]
	 */
	private $smallest_fault_set;

	/**
	 * Create a verdict.
	 *
	 * @param string      $type               One of the class constants.
	 * @param string|null $culprit         Guilty plugin ID, for CULPRIT_FOUND.
	 * @param string[]    $smallest_fault_set Smallest set seen producing the fault.
	 */
	public function __construct( string $type, $culprit = null, array $smallest_fault_set = array() ) {
		$this->type               = $type;
		$this->culprit            = $culprit;
		$this->smallest_fault_set = array_values( $smallest_fault_set );
	}

	/**
	 * The verdict type: one of the class constants.
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * The guilty plugin ID, or null unless the type is CULPRIT_FOUND.
	 *
	 * @return string|null
	 */
	public function culprit() {
		return $this->culprit;
	}

	/**
	 * The smallest set of plugins seen producing the fault.
	 *
	 * @return string[]
	 */
	public function smallest_fault_set(): array {
		return $this->smallest_fault_set;
	}

	/**
	 * Serialize to a plain array, for storage between requests.
	 */
	public function to_array(): array {
		return array(
			'type'             => $this->type,
			'culprit'          => $this->culprit,
			'smallestFaultSet' => $this->smallest_fault_set,
		);
	}

	/**
	 * Restore from {@see to_array()} output.
	 *
	 * @param array $data Output of to_array().
	 */
	public static function from_array( array $data ): self {
		return new self(
			(string) $data['type'],
			isset( $data['culprit'] ) ? (string) $data['culprit'] : null,
			isset( $data['smallestFaultSet'] ) ? array_values( (array) $data['smallestFaultSet'] ) : array()
		);
	}
}
