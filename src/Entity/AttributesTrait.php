<?php

namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use function Symfony\Component\String\u;

trait AttributesTrait
{
    #[ORM\Column(type: 'json', nullable: true)]
    protected $attributes = [];

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function setAttribute($attribute, $value): self
    {

        // total hack!
        $map = [
            'Nombre de la Obra' => 'setTitle',
            'Observaciones' => 'setDescription',
            'Número de Control Interno' => 'setStopId'
        ];
        if (isset($map[$attribute])) {
            $method = $map[$attribute];
            if (in_array($method, ['setTitle'] )) {
                $value = u($value)->lower()->title(true);
            }
            $this->$method($value);
        }
        if ($value !== null) {
                $this->attributes[$attribute] = u($value)->ascii()->trim()->toString();
            try {
            } catch (\Exception $e) {
                $this->attributes[$attribute] = $e->getMessage();
            }
        }

        return $this;
    }

    public function getAttribute($attribute, $throwError = false)
    {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        } else {
            if ($throwError) {
                throw new \Exception("Missing attribute $attribute for item");
            } else {
                return null;
            }
        }
    }

}
