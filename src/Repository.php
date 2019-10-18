<?php

namespace Dashifen\Repository;

/**
 * Class Repository
 * @package Dashifen\Repository
 *
 * The default Repository implementation which allows read-only access to all
 * properties by returning an empty array via the getHiddenPropertyNames()
 * method.  As long as your Repository isn't hiding anything, you can just
 * extend this one.
 */
class Repository extends AbstractRepository {
	/**
	 * getHiddenPropertyNames
	 *
	 * Returns an array of protected properties that shouldn't be returned
	 * by the __get() method or an empty array if the should all have read
	 * access.
	 *
	 * @return array
	 */
	protected function getHiddenPropertyNames(): array {
		return [];
	}
}