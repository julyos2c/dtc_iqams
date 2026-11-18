<?php

namespace Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/** Opt-in probes against a pre-provisioned disposable server and restricted user. */
class AuditDatabasePrivilegesTest extends TestCase
{
    public function test_runtime_identity_can_insert_and_read_but_cannot_mutate_audit_history(): void
    {
        if (getenv('IQAMS_AUDIT_PRIVILEGE_TEST') !== '1') {
            $this->markTestSkipped('Requires the separately provisioned disposable audit privilege server.');
        }

        $user = getenv('IQAMS_AUDIT_TEST_USER');
        $password = getenv('IQAMS_AUDIT_TEST_PASSWORD');
        if (! $user || $password === false || in_array(strtolower($user), ['root', 'admin'], true)) {
            $this->fail('Supply a restricted disposable test identity, never an administrative user.');
        }
        // Fixed loopback, port and schema prevent accidental use of the app connection.
        $db = new PDO('mysql:host=127.0.0.1;port=13318;dbname=iqams_audit_privilege_test;charset=utf8mb4', $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $id = random_int(1000000000, 1999999999);
        $insert = $db->prepare('INSERT INTO audit_logs (id, action, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
        $insert->execute([$id, 'privilege.test']);
        $read = $db->prepare('SELECT action FROM audit_logs WHERE id = ?');
        $read->execute([$id]);
        $this->assertSame('privilege.test', $read->fetchColumn());

        foreach ([
            "UPDATE audit_logs SET action = 'changed' WHERE id = {$id}",
            "DELETE FROM audit_logs WHERE id = {$id}",
            'TRUNCATE TABLE audit_logs',
            'ALTER TABLE audit_logs ADD COLUMN unauthorized_probe INT NULL',
            'DROP TABLE audit_logs',
        ] as $sql) {
            try {
                $db->exec($sql);
                $this->fail('A destructive operation unexpectedly succeeded on the disposable server.');
            } catch (PDOException $exception) {
                // MySQL/MariaDB privilege errors, not unrelated SQL or connectivity failures.
                $this->assertContains((int) ($exception->errorInfo[1] ?? 0), [1044, 1142, 1227]);
            }
        }
        $read->execute([$id]);
        $this->assertSame('privilege.test', $read->fetchColumn());
        // The restricted user cannot clean up; discard the entire disposable server.
    }
}
