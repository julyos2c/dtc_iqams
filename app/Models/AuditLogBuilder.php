<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** Guards Eloquent bulk mutations; direct database queries remain unprotected. */
class AuditLogBuilder extends Builder
{
    public function update(array $values) { throw new LogicException('Audit logs are append-only.'); }
    public function delete() { throw new LogicException('Audit logs are append-only.'); }
    public function forceDelete() { throw new LogicException('Audit logs are append-only.'); }
    public function increment($column, $amount = 1, array $extra = []) { throw new LogicException('Audit logs are append-only.'); }
    public function decrement($column, $amount = 1, array $extra = []) { throw new LogicException('Audit logs are append-only.'); }
    public function incrementEach(array $columns, array $extra = []) { throw new LogicException('Audit logs are append-only.'); }
    public function decrementEach(array $columns, array $extra = []) { throw new LogicException('Audit logs are append-only.'); }
    public function upsert(array $values, $uniqueBy, $update = null) { throw new LogicException('Audit logs are append-only.'); }
    public function updateOrInsert(array $attributes, array|callable $values = []) { throw new LogicException('Audit logs are append-only.'); }
    public function truncate() { throw new LogicException('Audit logs are append-only.'); }
    public function updateFrom(array $values) { throw new LogicException('Audit logs are append-only.'); }
}
