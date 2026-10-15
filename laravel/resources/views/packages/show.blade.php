@include('packages.index', [
    'pageTitle' => $package->name,
    'packages' => collect([$package]),
    'lastTrainingDays' => [$package->id => $lastTrainingDay->format('d/m/Y')],
])
