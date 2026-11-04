<?php
// Deliberately without strict_types: the monetary API must reject coercible floats here too.
return static fn($party) => new IntelligentIntern\SepaCreator\CreditTransfer('WEAK-CALLER', $party, 0.1);
