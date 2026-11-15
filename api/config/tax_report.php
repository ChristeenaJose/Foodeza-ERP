<?php

return [
  'legal_form' => 'Einzelunternehmen',
  'tax_regime' => 'Kleinunternehmerregelung (§ 19 UStG)',
  'accounting_method' => 'Einnahmen-Überschuss-Rechnung (EÜR)',
  'accounting_basis' => 'Zufluss-Abfluss-Prinzip (Buchungsdatum)',

  'income_service_map' => [
    'Catering' => 'Catering revenue',
    'Food Truck' => 'Food truck sales',
    'Corporate Lunch' => 'Event income',
    'Meal Box Delivery' => 'Other business income',
  ],

  'category_groups' => [
    'Ingredients' => 'operating',
    'Packaging' => 'operating',
    'Fuel' => 'operating',
    'Fuel car' => 'operating',
    'Fuel foodtruck' => 'operating',
    'Gas' => 'operating',
    'Insurance' => 'operating',
    'Marketing' => 'operating',
    'Service' => 'operating',
    'Website & Domain' => 'operating',
    'Vehicle Repairs & Maintenance' => 'operating',
    'Rent' => 'operating',
    'Salaries & wages' => 'operating',
    'Onam Köln 26' => 'operating',
    'Registration' => 'startup',
    'Registration fees' => 'startup',
    'Vehicle inspection' => 'startup',
    'Vehicle registration' => 'startup',
    'Equipment' => 'review',
    'Fixed assets' => 'fixed_asset',
    'Startup costs' => 'startup',
  ],

  'operating_labels' => [
    'Ingredients' => 'Food and ingredients',
    'Packaging' => 'Packaging and disposables',
    'Fuel' => 'Fuel and vehicle running costs',
    'Fuel car' => 'Fuel and vehicle running costs',
    'Fuel foodtruck' => 'Fuel and vehicle running costs',
    'Gas' => 'Fuel and vehicle running costs',
    'Insurance' => 'Insurance',
    'Marketing' => 'Marketing and advertising',
    'Service' => 'Professional services',
    'Website & Domain' => 'Website and domain costs',
    'Vehicle Repairs & Maintenance' => 'Fuel and vehicle running costs',
    'Rent' => 'Other operating expenses',
    'Salaries & wages' => 'Salaries and wages',
    'Onam Köln 26' => 'Other operating expenses',
    'misc_supplies' => 'Food and ingredients',
    'misc_vehicle' => 'Fuel and vehicle running costs',
    'misc_office' => 'Office expenses',
    'misc_other' => 'Other operating expenses',
  ],

  'startup_labels' => [
    'Registration' => 'Business registration fees',
    'Registration fees' => 'Vehicle registration costs',
    'Vehicle inspection' => 'TÜV costs',
    'Vehicle registration' => 'Vehicle registration costs',
    'gas_inspection' => 'Gas inspection costs',
    'gewerbe' => 'Gewerbeanmeldung',
    'other_startup' => 'Other startup expenses',
    'Fixed assets' => 'Capital investment',
    'Startup costs' => 'Other startup expenses',
  ],

  'asset_keywords' => [
    'foodtruck', 'food truck', 'buy van', 'van', 'sprinter', 'gasanlage', 'gas installation',
    'refrigerat', 'kühl', 'kitchen', 'küche', 'pos', 'kasse', 'renovation', 'umbau',
  ],

  'startup_keywords' => [
    'tüv', 'hu/au', 'inspection', 'nachuntersuchung', 'zulassung', 'registration', 'kennzeichen',
    'gewerbe', 'coc', 'gas prüfung', 'gasprüfung', 'gasanlage',
  ],

  'supply_supplier_keywords' => [
    'metro', 'sellgros', 'lidl', 'aldi', 'rewe', 'edeka', 'kaufland', 'asian', 'indian store', 'jp asian',
  ],

  'gwg_threshold_net' => 800.0,

  'afa_suggestions' => [
    'food_truck' => 'PKW/LKW AfA (linear, Nutzungsdauer 8–9 Jahre) — Steuerberater prüfen',
    'vehicle' => 'PKW/LKW AfA (linear, Nutzungsdauer 8–9 Jahre) — Steuerberater prüfen',
    'kitchen_equipment' => 'Betriebsausstattung AfA (linear, 8–13 Jahre)',
    'gas_installation' => 'Betriebsausstattung / Herstellungskosten — AfA oder Aktivierung',
    'computer' => 'Computer AfA (linear, 3 Jahre) oder GWG',
    'gwg' => 'Geringwertiges Wirtschaftsgut (GWG) — Sofortabschreibung möglich',
    'default' => 'AfA-Klasse durch Steuerberater festzulegen',
  ],

  'eur_lines' => [
    'income' => 'Zeile 14 – Betriebseinnahmen',
    'operating_expenses' => 'Zeile 35–64 – Betriebsausgaben',
    'depreciation' => 'Zeile 45 – AfA auf Anlagegüter',
    'startup' => 'Gründungs-/Anlaufkosten (Sonderbehandlung — Steuerberater)',
    'profit' => 'Gewinn/Verlust aus Gewerbebetrieb',
  ],
];
