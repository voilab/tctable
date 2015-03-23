# Voilab TcTable for TCPDF

## Install

Via Composer

Create a composer.json file in your project root:
``` json
{
    "require": {
        "voilab/tctable": "1.*"
    }
}
```

``` bash
$ composer require voilab/tctable
```

## Usage
```php
$pdf = new \TCPDF();

$minRowHeight = 6; //mm
$minWidows = 2;

$tctable = new \voilab\tctable\TcTable($pdf, $minRowHeight, $minWidows);
$tctable
    ->addColumn('description', [
        'isMultiLine' => true,
        'header' => 'Description',
        'width' => 100
    ])
    ->addColumn('quantity', [
        'header' => 'Quantity',
        'width' => 20,
        'align' => 'R'
    ])
    ->addColumn('price', [
        'header' => 'Price',
        'width' => 20,
        'align' => 'R'
    ]);

// get rows data
$rows = getMyDatas();

$tctable->addBody($rows, function (\voilab\tctable\TcTable $table, $row) {
    $change_rate = 0.8;
    // map row data to TcTable column definitions
    return [
        'description' => $row->getDescription(),
        'quantity' => $row->getQuantity(),
        'price' => $row->getPrice() * $change_rate
    ];
});

$pdf->Output('tctable.pdf', 'I');
```

### To do
Image insertion is extremely experimental...

## Testing
No test currently written...
``` bash
$ phpunit
```

## Security

If you discover any security related issues, please use the issue tracker.

## Credits

- [tafel](https://github.com/tafel)
- [voilab](https://github.com/voilab)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
