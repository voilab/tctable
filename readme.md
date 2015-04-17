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
The goal of this library is not to replace a complex HTML table with row and
cell spans. It's mainly useful if you want to display loads of lines (for an
invoice, etc.) and be sure page breaks are in the right place. You can adapt
the number of widow lines (minimum of lines that must appear on the last page),
and use plugins to customize the flow (see below).

###  Basic usage
```php
$pdf = new \TCPDF();
$minRowHeight = 6; //mm

$tctable = new \voilab\tctable\TcTable($pdf, $minRowHeight);
$tctable->setColumns([
    'description' => [
        'isMultiLine' => true,
        'header' => 'Description',
        'width' => 100
        // check inline documentation to see what options are available.
        // Basically, it's everything TCPDF Cell, MultiCell and Image can eat.
    ],
    'quantity' => [
        'header' => 'Quantity',
        'width' => 20,
        'align' => 'R'
    ],
    'price' => [
        'header' => 'Price',
        'width' => 20,
        'align' => 'R'
    ]
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

### Have a column that fit the remaining page width
```php
$tctable
    ->addPlugin(\voilab\tctable\plugin\FitColumn('text'))
    ->addColumn('text', [
        'isMultiLine' => true,
        'header' => 'Text'
        // no need to set width here, the plugin will calculate it for us,
        // depending on the other columns width
    ]);
```

### Stripe rows
```php
$tctable
    // set true to have the first line with colored background
    ->addPlugin(\voilab\tctable\plugin\StripeRows(true));
```

### Custom events
TcTable triggers some events we can listen to. It allows us to add some
usefull methods.

#### Add headers on each new page
```php
use \voilab\tctable\TcTable;
// ... create tctable

$tctable
    // when a page is added, draw headers
    ->on(TcTable::EV_PAGE_ADDED, function(TcTable $t) {
        $t->addHeader();
    })
    // just before headers are drawn, set font style to bold
    ->on(TcTable::EV_HEADER_ADD, function(TcTable $t) {
        $t->getPdf()->SetFont('', 'B');
    })
    // after headers are drawn, reset the font style
    ->on(TcTable::EV_HEADER_ADDED, function(TcTable $t) {
        $t->getPdf()->SetFont('', '');
    });
```

#### Advanced plugin: draw a subtotal for a column at end of each page
We can go further by calculating a sum for a column, and display the current
sum at the end of the page, and finally report it on the next page.
```php
<?php

namespace your\namespace;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class Report implements Plugin {

    // column on which we sum its value
    private $column;
    // the current calculated sum
    private $currentSum = 0;

    public function __construct($column) {
        $this->column = $column;
    }

    public function configure(TcTable $table) {
        $table
            // when launching the main process, reset sum at 0
            ->on(TcTable::EV_BODY_ADD, [$this, 'resetSum'])
            // after each added row, add the value to the sum
            ->on(TcTable::EV_ROW_ADDED, [$this, 'makeSum'])
            // when a page is added, draw the "subtotal" string
            ->on(TcTable::EV_PAGE_ADD, [$this, 'addSubTotal'])
            // after a page is added, draw the "sum from previous page" string
            ->on(TcTable::EV_PAGE_ADDED, [$this, 'addReport']);
    }

    public function resetSum() {
        $this->currentSum = 0;
    }

    public function makeSum(TcTable $table, $data) {
        $this->currentSum += $data[$this->column] * 1;
    }

    public function getSum() {
        return $this->currentSum;
    }

    public function addSubTotal(TcTable $table) {
        $pdf = $table->getPdf();
        $pdf->SetFont('', 'I');
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell($table->getColumnWidthUntil($this->column), $table->getColumnHeight(), "Sub-total:", false, false, 'R');
        $pdf->Cell($table->getColumnWidth($this->column), $table->getColumnHeight(), $this->currentSum, false, false, 'R');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '');
    }

    public function addReport(TcTable $table) {
        $pdf = $table->getPdf();
        $pdf->SetFont('', 'I');
        $pdf->SetTextColor(150, 150, 150);
        $table->getPdf()->Cell($table->getColumnWidthUntil($this->column), $table->getColumnHeight(), "Sum from previous page", 'B', false, 'R');
        $table->getPdf()->Cell($table->getColumnWidth($this->column), $table->getColumnHeight(), $this->currentSum, 'B', true, 'R');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '');
    }

}
```
And the TcTable
```php
$tctable->addPlugin(\your\namespace\Report('total'));
```

### Renderer and body functions

When parsing data, you can define either a renderer function for each or some
columns, or an anonymous function when calling $tctable->addBody(). These
functions are called twice, because it is needed in the process for height
calculation. You need to take that into account in certain cases.

```php
$total = 0;
$tctable->addBody($rows, function (TcTable $t, $row, $isHeightCalculation) use (&$total) {
    // if $height is true, it means this method is called when height
    // calculation is running. If we want to do a sum, we check first if
    // $isHeightCalculation is false, so it means the func is called during row
    // draw.
    if (!$isHeightCalculation) {
        $total += $row->getSomeValue();
    }
    // you still need to return the data regardless of $isHeightCalculation
    return [
        'description' => $row->getDescription(),
        'value' => $row->getSomeValue()
    ];
});

echo sprintf('Total: %d', $total);
```

The same idea applies to column renderers.

> *Note*
> In cases like the one above (creating a sum), you better should use plugins
> or events. With the event _TcTable::EV_ROW_ADDED_, you can do exactely the
> same thing without bothering with height calculation (see below).

```php
$total = 0;
$tctable
    ->on(TcTable::EV_ROW_ADDED, function (TcTable $t, $row) use (&$total) {
        $total += $row->getSomeValue();
    })
    ->addBody($rows, function (TcTable $t, $row) {
        return [
            'description' => $row->getDescription(),
            'value' => $row->getSomeValue()
        ];
    });

echo sprintf('Total: %d', $total);
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
