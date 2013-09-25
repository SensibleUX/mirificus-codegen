<?php
	namespace Mirificus;
	/**
	 * The exception that is thrown by static::Cast
	 * if an invalid cast is performed.  InvalidCastException
	 * derives from CallerException, and therefore should be handled
	 * similar to how CallerExceptions are handled (e.g. IncrementOffset should
	 * be called whenever an InvalidCastException is caught and rethrown).
	 */
//	class QInvalidCastException extends Exception {
//		public function __construct($strMessage, $intOffset = 2) {
//			parent::__construct($strMessage, $intOffset);
//		}
//	}

	/**
	 * Type Library to add some support for strongly named types.
	 *
	 * PHP does not support strongly named types.  The QCubed type library
	 * and QCubed typing in general attempts to bring some structure to types
	 * when passing in values, properties, parameters to/from QCubed framework objects
	 * and methods.
	 *
	 * The Type library attempts to allow as much flexibility as possible to
	 * set and cast variables to other types, similar to how PHP does it natively,
	 * but simply adds a big more structure to it.
	 *
	 * For example, regardless if a variable is an integer, boolean, or string,
	 * static::Cast will allow the flexibility of those values to interchange with
	 * each other with little to no issue.
	 *
	 * In addition to value objects (ints, bools, floats, strings), the Type library
	 * also supports object casting.  While technically casting one object to another
	 * is not a true cast, static::Cast does at least ensure that the tap being "casted"
	 * to is a legitamate subclass of the object being "cast".  So if you have ParentClass,
	 * and you have a ChildClass that extends ParentClass,
	 *		$objChildClass = new ChildClass();
	 *		$objParentClass = new ParentClass();
	 *		Type::Cast($objChildClass, 'ParentClass'); // is a legal cast
	 *		Type::Cast($objParentClass, 'ChildClass'); // will throw an InvalidCastException
	 *
	 * For values, specifically int to string conversion, one different between
	 * static::Cast and PHP (in order to add structure) is that if an integer contains
	 * alpha characters, PHP would normally allow that through w/o complaint, simply
	 * ignoring any numeric characters past the first alpha character.  static::Cast
	 * would instead throw an InvalidCastException to let the developer immedaitely
	 * know that something doesn't look right.
	 *
	 * In theory, the type library should maintain the same level of flexibility
	 * PHP developers are accostomed to, while providing a mechanism to limit
	 * careless coding errors and tough to figure out mistakes due to PHP's sometimes
	 * overly laxed type conversions.
	 */
	abstract class Type {
		/**
		 * This faux constructor method throws a caller exception.
		 * The Type object should never be instantiated, and this constructor
		 * override simply guarantees it.
		 *
		 * @return void
		 */
		//public final function __construct() {
			//throw new Exception('Type should never be instantiated.  All methods and variables are publically statically accessible.');
		//}

		const String = 'string';
		const Integer = 'integer';
		const Float = 'double';
		const Boolean = 'boolean';
		const Object = 'object';
		const ArrayType = 'array';

		const DateTime = 'QDateTime';
		
		const Resource = 'resource';

		const NoOp = 1;
		const CheckOnly = 2;
		const CastOnly = 3;
		const CheckAndCast = 4;
		private static $intBehaviour = self::CheckAndCast;

		private static function CastObjectTo($objItem, $strType) {
			try {
				$objReflection = new \ReflectionClass($objItem);
				if ($objReflection->getName() == 'SimpleXMLElement') {
					switch ($strType) {
						case static::String:
							return (string) $objItem;
						case static::Integer:
							try {
								return static::Cast((string) $objItem, static::Integer);
							} catch (Exception $objExc) {
								$objExc->IncrementOffset();
								throw $objExc;
							}
						case static::Boolean:
							$strItem = strtolower(trim((string) $objItem));
							if (($strItem == 'false') ||
								(!$strItem))
								return false;
							else
								return true;
					}
				}

				if ($objItem instanceof $strType)
					return $objItem;
			} catch (Exception $objExc) {
			}

			throw new QInvalidCastException(sprintf('Unable to cast %s object to %s', $objReflection->getName(), $strType));
		}

		private static function CastValueTo($mixItem, $strNewType) {
			$strOriginalType = gettype($mixItem);

			switch (static::TypeFromDoc($strNewType)) {
				case static::Boolean:
					if ($strOriginalType == static::Boolean)
						return $mixItem;
					if (is_null($mixItem))
						return false;
					if (strlen($mixItem) == 0)
						return false;
					if (strtolower($mixItem) == 'false')
						return false;
					settype($mixItem, $strNewType);
					return $mixItem;

				case static::Integer:
					if($strOriginalType == static::Boolean)
						throw new QInvalidCastException(sprintf('Unable to cast %s value to %s: %s', $strOriginalType, $strNewType, $mixItem));
					if (strlen($mixItem) == 0)
						return null;
					if ($strOriginalType == static::Integer)
						return $mixItem;
					
					// Check to make sure the value hasn't changed significantly
					$intItem = $mixItem;
					settype($intItem, $strNewType);
					$mixTest = $intItem;
					settype($mixTest, $strOriginalType);
					
					// If the value hasn't changed, it's safe to return the casted value
					if ((string)$mixTest === (string)$mixItem)
						return $intItem;
					
					// if casting changed the value, but we have a valid integer, return with a string cast
					if (preg_match('/^-?\d+$/',$mixItem) === 1)
						return (string)$mixItem;
					
					// any other scenarios is an invalid cast
					throw new QInvalidCastException(sprintf('Unable to cast %s value to %s: %s', $strOriginalType, $strNewType, $mixItem));
				case static::Float:
					if($strOriginalType == static::Boolean)
						throw new QInvalidCastException(sprintf('Unable to cast %s value to %s: %s', $strOriginalType, $strNewType, $mixItem));
					if (strlen($mixItem) == 0)
						return null;
					if ($strOriginalType == static::Float)
						return $mixItem;

					if (!is_numeric($mixItem)) 
						throw new QInvalidCastException(sprintf('Invalid float: %s', $mixItem)); 
					
					// Check to make sure the value hasn't changed significantly
					$fltItem = $mixItem;
					settype($fltItem, $strNewType);
					$mixTest = $fltItem;
					settype($mixTest, $strOriginalType);
					
					//account for any scientific notation that results
					//find out what notation is currently being used
					$i = strpos($mixItem, '.');
					$precision = ($i === false) ? 0 : strlen($mixItem) - $i - 1;
					//and represent the casted value the same way
					$strTest = sprintf('%.' . $precision . 'f', $fltItem);

					// If the value hasn't changed, it's safe to return the casted value
					if ((string)$strTest === (string)$mixItem)
						return $fltItem;
					
					// the changed value could be the result of loosing precision. Return the original value with no cast
					return $mixItem;
			
				case static::String:
					if ($strOriginalType == static::String)
						return $mixItem;
					
					// Check to make sure the value hasn't changed significantly
					$strItem = $mixItem;
					settype($strItem, $strNewType);
					$mixTest = $strItem;
					settype($mixTest, $strOriginalType);
					
					// Has it?
					$blnSame = true; 
					if ($strOriginalType == static::Float) { 
						// type conversion from float to string affects precision and can throw off the comparison 
						// so we need to use a comparison check using an epsilon value instead 
						$epsilon = 1.0e-14; 
						$diff = abs($mixItem - $mixTest); 
						if ($diff > $epsilon) { 
							$blnSame = false; 
						} 
					} 
					else { 
						if ($mixTest != $mixItem) 
						$blnSame = false; 
					} 
					if (!$blnSame) 
						//This is an invalid cast
						throw new QInvalidCastException(sprintf('Unable to cast %s value to %s: %s', $strOriginalType, $strNewType, $mixItem));
					
					return $strItem;

				default:
					throw new QInvalidCastException(sprintf('Unable to cast %s value to unknown type %s', $strOriginalType, $strNewType));
			}
		}
		
		private static function CastArrayTo($arrItem, $strType) {
			if ($strType == static::ArrayType)
				return $arrItem;
			else
				throw new QInvalidCastException(sprintf('Unable to cast Array to %s', $strType));
		}

		/**
		 * This method can be used to change the casting behaviour of static::Cast().
		 * By default static::Cast() does lots of validation and type casting (using settype()).
		 * Depending on your application you may or may not need validation or casting or both.
		 * In these situations you can set the necessary behaviour by passing the appropriate constant to this function.
		 *
		 * @static
		 * @param int $intBehaviour one of the 4 constants static::NoOp, static::CastOnly, static::CheckOnly, static::CheckAndCast
		 * @return int the previous setting
		 */
		public static function SetBehaviour($intBehaviour) {
			$oldBehaviour = static::$intBehaviour;
			static::$intBehaviour = $intBehaviour;
			return $oldBehaviour;
		}

		/**
		 * Used to cast a variable to another type.  Allows for moderate
		 * support of strongly-named types.
		 *
		 * Will throw an exception if the cast fails, causes unexpected side effects,
		 * if attempting to cast an object to a value (or vice versa), or if an object
		 * is being cast to a class that isn't a subclass (e.g. parent).  The exception
		 * thrown will be an InvalidCastException, which extends CallerException.
		 *
		 * @param mixed $mixItem the value, array or object that you want to cast
		 * @param string $strType the type to cast to.  Can be a static::XXX constant (e.g. static::Integer), or the name of a Class
		 * @return mixed the passed in value/array/object that has been cast to strType
		 */
		public final static function Cast($mixItem, $strType) {
			switch (static::$intBehaviour) {
				case static::NoOp:
					return $mixItem;
				case static::CastOnly:
					throw new Exception("static::CastOnly handling not yet implemented");
					break;
				case static::CheckOnly:
					throw new Exception("static::CheckOnly handling not yet implemented");
					break;
				case static::CheckAndCast:
					break;
				default:
					throw new InvalidArgumentException();
					break;
			}
			// Automatically Return NULLs
			if (is_null($mixItem))
				return null;

			// Figure out what PHP thinks the type is
			$strPhpType = gettype($mixItem);

			switch ($strPhpType) {
				case static::Object:
					try {
						return static::CastObjectTo($mixItem, $strType);
					} catch (Exception $objExc) {
						$objExc->IncrementOffset();
						throw $objExc;
					}

				case static::String:
				case static::Integer:
				case static::Float:
				case static::Boolean:
					try {
						return static::CastValueTo($mixItem, $strType);
					} catch (Exception $objExc) {
						$objExc->IncrementOffset();
						throw $objExc;
					}

				case static::ArrayType:
					try {
						return static::CastArrayTo($mixItem, $strType);
					} catch (Exception $objExc) {
						$objExc->IncrementOffset();
						throw $objExc;
					}

				case static::Resource:
					// Cannot Cast Resources
					throw new QInvalidCastException('Resources cannot be cast');

				default:
					// Could not determine type
					throw new QInvalidCastException(sprintf('Unable to determine type of item to be cast: %s', $mixItem));
			}
		}
		
		/**
		 * Used by the QCubed Code Generator to allow for the code generation of
		 * the actual "Type::Xxx" constant, instead of the text of the constant,
		 * in generated code.
		 *
		 * It is rare for Constant to be used manually outside of Code Generation.
		 *
		 * @param string $strType the type to convert to 'constant' form
		 * @return string the text of the Text:Xxx Constant
		 */
		public final static function Constant($strType) {
			switch ($strType) {
				case static::Object: return 'static::Object';
				case static::String: return 'static::String';
				case static::Integer: return 'static::Integer';
				case static::Float: return 'static::Float';
				case static::Boolean: return 'static::Boolean';
				case static::ArrayType: return 'static::ArrayType';
				case static::Resource: return 'static::Resource';
				case static::DateTime: return 'static::DateTime';

				default:
					// Could not determine type
					throw new QInvalidCastException(sprintf('Unable to determine type of item to lookup its constant: %s', $strType));
			}
		}
		
		public final static function TypeFromDoc($strType) {
			switch (strtolower($strType)) {
				case 'string':
				case 'str':
					return static::String;

				case 'integer':
				case 'int':
					return static::Integer;

				case 'float':
				case 'flt':
				case 'double':
				case 'dbl':
				case 'single':
				case 'decimal':
					return static::Float;

				case 'bool':
				case 'boolean':
				case 'bit':
					return static::Boolean;

				case 'datetime':
				case 'date':
				case 'time':
				case 'qdatetime':
					return static::DateTime;

				case 'null':
				case 'void':
					return 'void';

				default:
					try {
						$objReflection = new \ReflectionClass($strType);
						return $strType;
					} catch (ReflectionException $objExc) {
						throw new QInvalidCastException(sprintf('Unable to determine type of item from PHPDoc Comment to lookup its static or Class: %s', $strType));
					}
			}
		}
		
		/**
		 * Used by the QCubed Code Generator and QSoapService class to allow for the xml generation of
		 * the actual "s:type" Soap Variable types.
		 *
		 * @param string $strType the type to convert to 'constant' form
		 * @return string the text of the SOAP standard s:type variable type
		 */
		public final static function SoapType($strType) {
			switch ($strType) {
				case static::String: return 'string';
				case static::Integer: return 'int';
				case static::Float: return 'float';
				case static::Boolean: return 'boolean';
				case static::DateTime: return 'dateTime';

				case static::ArrayType:
				case static::Object:
				case static::Resource:
				default:
					// Could not determine type
					throw new QInvalidCastException(sprintf('Unable to determine type of item to lookup its constant: %s', $strType));
			}
		}
/*
		final public static function SoapArrayType($strType) {
			try {
				return sprintf('ArrayOf%s', ucfirst(static::SoapType($strType)));
			} catch (QInvalidCastException $objExc) {}
				$objExc->IncrementOffset();
				throw $objExc;
			}
		}

		final public static function AlterSoapComplexTypeArray(&$strComplexTypeArray, $strType) {
			switch ($strType) {
				case static::String:
					$strItemName = 'string';
					break;
				case static::Integer:
					$strItemName = 'int';
					break;
				case static::Float:
					$strItemName = 'float';
					break;
				case static::Boolean:
					$strItemName = 'boolean';
					break;
				case static::DateTime:
					$strItemName = 'dateTime';
					break;

				case static::ArrayType:
				case static::Object:
				case static::Resource:
				default:
					// Could not determine type
					throw new QInvalidCastException(sprintf('Unable to determine type of item to lookup its constant: %s', $strType));
			}

			$strArrayName = static::SoapArrayType($strType);

			if (!array_key_exists($strArrayName, $strComplexTypeArray))
				$strComplexTypeArray[$strArrayName] = sprintf(
					'<s:complexType name="%s"><s:sequence>' . 
					'<s:element minOccurs="0" maxOccurs="unbounded" name="%s" type="%s"/>' .
					'</s:sequence></s:complexType>',
					static::SoapArrayType($strType),
					$strItemName,
					static::SoapType($strType));
		}*/
	}
?>
