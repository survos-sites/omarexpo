<?php
namespace App\Entity;

use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;

class SurvosBaseEntity implements RouteParametersInterface
{
    use RouteParametersTrait;

    public function populateFromOptions(array $options ): self
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
//        unset($options['_token']);
//        unset($options['_next_route']);
        foreach ($options as $var=>$val) {

            // isn't there a property accessor method?
            try {
                $propertyAccessor->setValue($this, $var, $val);
            } catch (NoSuchPropertyException $exception) {
                //
            } catch (\InvalidArgumentException $exception) {
                // it might be a date string
                try {
                    $date = new \DateTimeImmutable($val);
                    $propertyAccessor->setValue($this, $var, $date);

                } catch (\Exception $e) {
                    dump($var, $val, $e); assert(false);
                }

            }

//            if (method_exists($this, $setter = 'set' . $var)) {
//                try {
//                    $this->{$setter}($val);
//                } catch (\Exception $exception) {
//                    throw new \Exception($setter, $val);
//                }
//            } elseif (property_exists($this, $var)) {
//                $this->{$var} = $val;
////                dump($var, $val, $options, $this);
//            } else {
//                //not mapped.  @todo: check for source_date v sourceDate (and convert dates!)
////                throw new \Exception($var, $val, $options, $this, __METHOD__);
//            }
        }
//        throw new \Exception($options, $this, __METHOD__);
        return $this;
    }

}
