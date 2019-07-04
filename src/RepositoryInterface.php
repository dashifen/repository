<?php

namespace Dashifen\Container;

interface RepositoryInterface {
	/**
	 * toArray
	 *
	 * Returns the non-hidden protected properties of this object as an
	 * associative array mapping property name to value.
	 *
	 * @return array
	 */
	public function toArray(): array;
}
