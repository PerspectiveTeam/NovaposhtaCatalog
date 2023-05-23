<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface StreetInterface
{
    const DATABASE_TABLE_NAME = 'perspective_novaposhta_catalog_street';
    /**
     * String constants for property names
     */
    const STREET_ID = "id";
    const REF = "ref";
    const DESCRIPTION = "description";
    const STREET_TYPE_REF = "street_type_ref";
    const STREET_TYPE = "street_type";
    const CITY_REF = "city_ref";

    /**
     * Getter for StreetId.
     *
     * @return int
     */
    public function getStreetId(): int;

    /**
     * Setter for StreetId.
     *
     * @param int $streetId
     *
     * @return StreetInterface
     */
    public function setStreetId(int $streetId): StreetInterface;

    /**
     * Getter for Ref - Street Identifier.
     *
     * @return null|string
     */
    public function getRef(): ?string;

    /**
     * Setter for Ref - Street Identifier.
     *
     * @param string $ref
     *
     * @return StreetInterface
     */
    public function setRef(string $ref): StreetInterface;

    /**
     * Getter for Description - Street Name in UA language.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Setter for Description - Street Name in UA language.
     *
     * @param string $description
     *
     * @return StreetInterface
     */
    public function setDescription(string $description): StreetInterface;

    /**
     * Getter for StreetTypeRef.
     *
     * @return string
     */
    public function getStreetTypeRef(): string;

    /**
     * Setter for StreetTypeRef.
     *
     * @param string $streetTypeRef
     *
     * @return StreetInterface
     */
    public function setStreetTypeRef(string $streetTypeRef): StreetInterface;

    /**
     * Getter for StreetType.
     *
     * @return string
     */
    public function getStreetType(): string;

    /**
     * Setter for StreetType.
     *
     * @param string $streetType
     *
     * @return StreetInterface
     */
    public function setStreetType(string $streetType): StreetInterface;

    /**
     * Getter for CityRef.
     *
     * @return string
     */
    public function getCityRef(): string;

    /**
     * Setter for CityRef.
     *
     * @param string $cityRef
     *
     * @return StreetInterface
     */
    public function setCityRef(string $cityRef): StreetInterface;
}
