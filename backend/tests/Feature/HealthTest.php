<?php

use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'census-test-');
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec('
        CREATE TABLE uf (cd_uf TEXT PRIMARY KEY, nm_uf TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE municipio (cd_mun TEXT PRIMARY KEY, nm_mun TEXT NOT NULL, cd_uf TEXT NOT NULL REFERENCES uf(cd_uf)) WITHOUT ROWID;
        CREATE TABLE setor (cd_setor TEXT PRIMARY KEY, cd_mun TEXT NOT NULL REFERENCES municipio(cd_mun), situacao TEXT, area_km2 REAL, populacao INTEGER) WITHOUT ROWID;
        CREATE TABLE demografia (cd_setor TEXT PRIMARY KEY REFERENCES setor(cd_setor), moradores INTEGER, homens INTEGER, mulheres INTEGER) WITHOUT ROWID;
        INSERT INTO uf VALUES ("11", "Rondônia");
    ');
    $fixture = null;

    config(['database.connections.census.database' => $this->databasePath]);
    DB::purge('census');
});

afterEach(function (): void {
    DB::purge('census');

    if (is_file($this->databasePath)) {
        unlink($this->databasePath);
    }
});

it('reports readiness when all required census tables can be read', function (): void {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok']]);
});

it('rejects writes even if query_only is disabled on the connection', function (): void {
    $fixtureHash = hash_file('sha256', $this->databasePath);
    $connection = DB::connection('census');
    expect((int) $connection->selectOne('PRAGMA query_only')->query_only)->toBe(1);
    expect($connection->table('uf')->value('nm_uf'))->toBe('Rondônia');

    $connection->getPdo()->exec('PRAGMA query_only = OFF');

    expect(fn () => $connection->statement('CREATE TABLE forbidden (id INTEGER)'))
        ->toThrow(Illuminate\Database\QueryException::class);

    expect(hash_file('sha256', $this->databasePath))->toBe($fixtureHash);
});

it('returns a generic 503 without creating a missing database', function (): void {
    unlink($this->databasePath);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'Base censitária indisponível.']);

    expect(file_exists($this->databasePath))->toBeFalse();
});

it('rejects an incompatible schema', function (string $change): void {
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec($change);
    $fixture = null;

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'Base censitária indisponível.']);
})->with([
    'missing table' => ['DROP TABLE demografia'],
    'missing column' => ['ALTER TABLE demografia DROP COLUMN mulheres'],
]);

it('rejects a corrupted database', function (): void {
    file_put_contents($this->databasePath, 'not a SQLite database');

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'Base censitária indisponível.']);
});

it('returns JSON for unknown API routes without leaking internal details', function (): void {
    $this->get('/api/does-not-exist')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
});
