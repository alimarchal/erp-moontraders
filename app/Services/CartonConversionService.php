<?php

namespace App\Services;

use App\Models\Product;
use DomainException;

/**
 * Converts carton-mode GRN entry (cartons + loose pieces + rate per TH) into
 * pieces and cost. Stock always stays in pieces; this is only an input helper.
 */
class CartonConversionService
{
    /**
     * @return array{pieces: int, qty_th: float, total: float, unit_cost: float}
     *
     * @throws DomainException when the product has no carton/pouch configuration or the input is invalid
     */
    public function convert(Product $product, float $cartons, int $loosePieces, float $ratePerTh): array
    {
        if (! $product->pieces_per_carton || ! $product->units_per_piece) {
            throw new DomainException(sprintf(
                'Product "%s" needs "Pieces per carton" and "Pouches per piece" set before it can be received in carton mode.',
                $product->product_name
            ));
        }

        if ($cartons < 0 || $loosePieces < 0 || $ratePerTh < 0) {
            throw new DomainException('Cartons, loose pieces and rate per TH cannot be negative.');
        }

        $cartonPieces = $cartons * $product->pieces_per_carton;

        if (abs($cartonPieces - round($cartonPieces)) > 0.0001) {
            throw new DomainException(sprintf('Cartons for "%s" do not convert to a whole number of pieces.', $product->product_name));
        }

        $pieces = (int) round($cartonPieces) + $loosePieces;

        if ($pieces < 1) {
            throw new DomainException(sprintf('Enter cartons or loose pieces for "%s".', $product->product_name));
        }

        $qtyTh = $pieces * $product->units_per_piece / 1000;
        $total = round($qtyTh * $ratePerTh, 2);

        return [
            'pieces' => $pieces,
            'qty_th' => round($qtyTh, 3),
            'total' => $total,
            'unit_cost' => round($total / $pieces, 6),
        ];
    }
}
