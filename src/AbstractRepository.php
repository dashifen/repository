<?php

namespace Dashifen\Repository;

use ReflectionClass;
use JsonSerializable;
use ReflectionProperty;
use ReflectionException;

/**
 * Class AbstractRepository
 *
 * @package Dashifen\Repository
 */
abstract class AbstractRepository implements JsonSerializable, RepositoryInterface {
  /**
   * @var array
   */
  protected $__properties;

  /**
   * AbstractRepository constructor.
   *
   * If given an associative data array, loops over its values settings
   * properties that match indices therein.
   *
   * @param array $data
   *
   * @throws RepositoryException
   */
  public function __construct (array $data = []) {
    $this->initializeProperties();
    $this->setPropertyValues($data);
    $this->setDefaultPropertyValues();

    // now we've made our list of read-only properties, we've set them either
    // with values from $data or default values, so all that's left is to make
    // sure that we don't have any empty requirements.

    $emptyRequired = $this->findEmptyRequirements();
    if (($emptyCount = sizeof($emptyRequired)) > 0) {

      // if we do have empty requirements, we'll want to print a message about
      // them.  since the findEmptyRequirements() method returns a list of the
      // ones we need filled in, we'll tailor or message such that it displays
      // that list for the programmer to work with.

      $noun = $emptyCount === 1 ? "property" : "properties";
      $list = join(", ", $emptyRequired);

      throw new RepositoryException(
        "Please be sure to provide values for the following $noun:  $list",
        RepositoryException::EMPTY_REQUIREMENTS
      );
    }
  }

  /**
   * initializeProperties
   *
   * Uses a ReflectionClass to initialize an array of the names of public
   * and protected properties of our object that should be available via
   * the __get() method.
   *
   * @return void
   * @throws RepositoryException
   */
  final private function initializeProperties () {

    // first, we get a list of our property names.  then, we get a list
    // of the ones that should be hidden, and we force that second list
    // to include the $__properties property so that it's not available
    // to anyone other than us.  finally, we make sure anything in the
    // second list is removed from the first.

    $properties = $this->getPropertyNames();
    $hidden = array_merge(["__properties"], $this->getHiddenPropertyNames());
    $this->__properties = array_diff($properties, $hidden);

    // the final step is to be sure that we don't have both an optional and
    // required property of the same name.  required properties are prefixed
    // with two underscores, so we could have $this->foo and $this->__foo if
    // we're not careful.  first, we'll remove double underscores from the
    // start of property names.  then, we remove duplicates.  finally, we see
    // if we still have the same number of indices in both the altered array
    // and __properties.

    $mapped = array_map(function (string $property): string {
      return preg_replace('/^__/', '', $property);
    }, $this->__properties);

    $unique = array_unique($mapped);
    if (sizeof($this->__properties) !== sizeof($unique)) {
      throw new RepositoryException(
        "Please ensure that you do not have optional and required properties of the same name.",
        RepositoryException::DUPLICATE_PROPERTIES
      );
    }
  }

  /**
   * getPropertyNames
   *
   * Uses a ReflectionClass to initialize an array of the names of public
   * and protected properties.
   *
   * @return array
   */
  final private function getPropertyNames (): array {

    // we use the late static binding on our class name so that children
    // reflect themselves and not this object.  then, we get a list of
    // their properties such that they're

    try {
      $reflection = new ReflectionClass(static::class);
    } catch (ReflectionException $e) {

      // this shouldn't happen since we're reflecting our own object,
      // but if it ever does, there's nothing we can do but die.

      trigger_error("Unable to reflect self.", E_USER_ERROR);
      die();
    }

    $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);

    // we don't want an array of ReflectionProperty objects in the calling
    // scope.  so, we'll use array_map to loop over our list and return
    // only their names.

    return array_map(function (ReflectionProperty $property) {
      return $property->getName();
    }, $properties);
  }

  /**
   * getHiddenPropertyNames
   *
   * Returns an array of protected properties that shouldn't be returned
   * by the __get() method or an empty array if the should all have read
   * access.
   *
   * @return array
   */
  abstract protected function getHiddenPropertyNames (): array;

  /**
   * setPropertyValues
   *
   * Called from the AbstractRepository constructor, this method uses the
   * $data parameter of the constructor to set property values.
   *
   * @param array $data
   *
   * @throws RepositoryException
   */
  protected function setPropertyValues (array $data): void {
    foreach ($data as $property => $value) {

      // if our $property doesn't exist as-is, we'll try passing it
      // through one of our methods below to see if it's an HTML style
      // form field name that we can convert into a property's name
      // here.

      if (!property_exists($this, $property)) {
        $property = $this->convertFieldToProperty($property);
      }

      // now, if the property exists either in it's original format or
      // in the converted one, we'll proceed.  otherwise, an exception
      // is thrown in the else-block below.

      if (property_exists($this, $property)) {

        // now, we should have a setter for each of our properties.
        // if not, we'll throw an exception.  but, if we have a setter
        // for our property, we call it and pass in our value.

        $setter = "set" . ucfirst($property);
        if (method_exists($this, $setter)) {
          $this->{$setter}($value);
        } else {

          // if we find a property but it doesn't have a setter,
          // we're going to throw an exception.  children can always
          // modify this behavior if it's a problem.  or, apps can
          // catch and ignore them.  regardless, it seems worthwhile
          // to inform someone that they've probably forgotten to
          // write a method.

          throw new RepositoryException("Setter missing: $setter.",
            RepositoryException::UNKNOWN_SETTER);
        }
      } else {

        // similarly, if we receive data for which we do not have a
        // property, then we'll throw a different exception.  same
        // reasoning applies:  this could be a problem, and only the
        // programmer of the app using this object will know.

        throw new RepositoryException("Unknown property: $property.",
          RepositoryException::UNKNOWN_PROPERTY);
      }
    }
  }

  /**
   * convertFieldToProperty
   *
   * Given a string using dashes to separate words in the way HTML likes
   * it, return a camelCase string like PHP properties like it.
   *
   * @param string $field
   *
   * @return string
   */
  protected function convertFieldToProperty (string $field) {
    return preg_replace_callback("/-(\w)/", function ($matches) {

      // for any character preceded by a dash, we want to return the
      // capital version of that letter.  notice that this also removes
      // the dash since it's included in the match.  thus, event-name
      // becomes eventName.

      return strtoupper($matches[1]);
    }, $field);
  }

  /**
   * setDefaultPropertyValues
   *
   * For any property that has a default value, if it's current value is empty
   * (for all the various empty values in PHP), then we reset that value to its
   * default.
   *
   * @return void
   */
  protected function setDefaultPropertyValues (): void {
    $defaults = $this->getPropertyDefaults();

    echo "<pre>" . print_r($defaults, true) . "</pre>";

    foreach ($defaults as $property => $default) {
      if (empty($this->{$property})) {

        // if we made it all the way here, then this property is empty.
        // regardless of the exact nature of its emptiness (e.g. NULL vs. ''),
        // we'll reset it's value to its default.

        $this->{$property} = $default;
      }
    }
  }

  /**
   * getPropertyDefaults
   *
   * Returns an array mapping property names to their default value
   *
   * @return array
   */
  final private function getPropertyDefaults (): array {
    try {

      // we should always be able to reflect $this because the class is
      // already loaded or we wouldn't be here.  but, because we might throw
      // an exception, we'll wrap ths following return statement in a try/catch
      // block.

      $defaults = (new ReflectionClass(static::class))->getDefaultProperties();
      $defaults = array_merge($defaults, $this->getCustomPropertyDefaults());

      // finally, we want to make sure that our list of defaults (a) doesn't
      // include the __properties property and (b) it only includes properties
      // that are in the __properties array.  we also remove any that don't
      // have a default value while we're filtering.

      return array_filter($defaults, function ($default, string $property): bool {
        return strlen($default) !== 0                      // has a default
          && $property !== "__properties"                  // isn't __properties
          && in_array($property, $this->__properties);     // is in __properties
      }, ARRAY_FILTER_USE_BOTH);
    } catch (ReflectionException $e) {

      // in the vanishingly unlikely chance that we end up here, we'll just
      // return an empty array.  no properties will have their value set to
      // the default, but that's okay.

      return [];
    }
  }

  /**
   * getCustomPropertyDefaults
   *
   * Intended as a way to provide for functional defaults (e.g. the current
   * date), extensions can override this function to return an array of default
   * values for properties.  that array should be indexed by property names.
   *
   * @return array
   */
  abstract protected function getCustomPropertyDefaults (): array;

  /**
   * findEmptyRequirements
   *
   * Returns an array of empty required properties.
   *
   * @return array
   */
  protected function findEmptyRequirements (): array {
    $filter = function (string $i) {

      // we'll want to auto-require any properties that begin with __.  since
      // we only look within the __properties property for them, we don't have
      // to worry about skipping that one.

      return substr($i, 0, 2) === "__";
    };

    $requiredProperties = array_merge(
      array_filter($this->__properties, $filter),
      $this->getRequiredProperties()
    );

    $empties = [];
    foreach ($requiredProperties as $property) {
      if (empty($this->{$property})) {
        $empties[] = preg_replace('/^__/', '', $property);
      }
    }

    return $empties;
  }

  /**
   * getRequiredProperties
   *
   * Returns an array of property names that must be non-empty after
   * construction.
   *
   * @return array
   */
  abstract protected function getRequiredProperties (): array;

  /**
   * convertPropertyToField
   *
   * Given a camelCases string, return it using dashes to separate words
   * like HTML likes it.
   *
   * @param string $property
   *
   * @return string
   */
  protected function convertPropertyToField (string $property) {
    return preg_replace_callback("/(?<=[a-z])([A-Z])/", function ($matches) {

      // this time, any capital letter preceded by a lowercase one
      // is converted to a dash followed by the lowercase version of
      // it.  unlike the prior method, we need to leave the originally
      // lowercase letter in the string, so we use a positive look-
      // behind group to identify it.  thus, eventName becomes
      // event-name.

      return "-" . strtolower($matches[1]);
    }, $property);
  }

  /**
   * __get()
   *
   * Given the name of a property, if it's in the $__properties property,
   * return it's value.
   *
   * @param string $property
   *
   * @return mixed
   * @throws RepositoryException
   */
  public function __get (string $property) {

    // a property prefixed by two underscores (__) is required.  but, we want
    // to make it as simple as possible for the calling scope to reference
    // properties.  thus, we hide the existence of those underscores herein by
    // testing both property names both with and without them.

    if (
      !in_array($property, $this->__properties)
      && !in_array("__$property", $this->__properties)
    ) {
      throw new RepositoryException("Unknown property: $property.",
        RepositoryException::UNKNOWN_PROPERTY);
    }

    // normally, we can just return our property directly, but in case
    // someone wants to transform it before sending it back (e.g. changing
    // a date's format), if there is a getter for the requested property,
    // we'll use it.

    $getter = "get" . ucfirst($property);
    return !method_exists(static::class, $getter)

      // if we're not using the getter, we'll want to return either the
      // property directly named by $property or the required one which is
      // named with two underscores and then $property.  we know one of
      // these must exist because we tested for them above.

      ? ($this->{$property} ?? $this->{"__$property"})
      : $this->{$getter}();
  }

  /**
   * __isset()
   *
   * This simply returns true if the requested property exists and is
   * not hidden.  This is to make empty() and __get() play well together.
   *
   * @param string $property
   *
   * @return bool
   */
  public function __isset (string $property) {

    // just like the prior method, we want to test for both $property and
    // $property prefixed by __.  this allows us to internally reference
    // required properties with __ but makes that naming convention invisible
    // to the calling scope.

    return in_array($property, $this->__properties)
      || in_array("__$property", $this->__properties);
  }

  /**
   * jsonSerialize
   *
   * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
   *
   * @return array
   */
  public function jsonSerialize () {

    // that which we want to JSON-ify is the list of properties to which
    // __get() has access.  thus, we can loop over __properties adding them
    // to an array, and then, we return them.

    foreach ($this->__properties as $property) {
      $jsonData[$property] = $this->{$property};
    }

    return $jsonData ?? [];
  }

  /**
   * toArray
   *
   * Returns the non-hidden protected properties of this object as an
   * associative array mapping property name to value.
   *
   * @return array
   */
  public function toArray (): array {

    // what we do here is the same as what we do when serializing the
    // object to JSON.  so, we can just return that value here.  why
    // not just use that method everywhere?  because it's name is
    // unfamiliar while {$object}->toArray() is pretty common.

    return $this->jsonSerialize();
  }
}