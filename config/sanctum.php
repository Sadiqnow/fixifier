<?php

return [
    // Existing customer/technician clients use bearer tokens. Keep an admin web
    // session from replacing the identity supplied by those API clients.
    'guard' => [],
];
