using System.Data;
using Dapper;
using Npgsql;

namespace SaveraApi.Infrastructure;

public static class DbExtensions
{
    public static async Task<int> ExecuteAsync(this NpgsqlDataSource dataSource, string sql, object? param = null)
    {
        await using var conn = await dataSource.OpenConnectionAsync();
        return await conn.ExecuteAsync(sql, param);
    }

    public static async Task<IEnumerable<T>> QueryAsync<T>(this NpgsqlDataSource dataSource, string sql, object? param = null)
    {
        await using var conn = await dataSource.OpenConnectionAsync();
        return await conn.QueryAsync<T>(sql, param);
    }

    public static async Task<T?> QuerySingleOrDefaultAsync<T>(this NpgsqlDataSource dataSource, string sql, object? param = null)
    {
        await using var conn = await dataSource.OpenConnectionAsync();
        return await conn.QuerySingleOrDefaultAsync<T>(sql, param);
    }

    public static async Task<T?> QueryFirstOrDefaultAsync<T>(this NpgsqlDataSource dataSource, string sql, object? param = null)
    {
        await using var conn = await dataSource.OpenConnectionAsync();
        return await conn.QueryFirstOrDefaultAsync<T>(sql, param);
    }

    public static async Task<T> QuerySingleAsync<T>(this NpgsqlDataSource dataSource, string sql, object? param = null)
    {
        await using var conn = await dataSource.OpenConnectionAsync();
        return await conn.QuerySingleAsync<T>(sql, param);
    }

    public static async Task<object?> ExecuteScalarAsync(this NpgsqlDataSource dataSource, string sql, object? param = null)
    {
        await using var conn = await dataSource.OpenConnectionAsync();
        return await conn.ExecuteScalarAsync(sql, param);
    }

    public static async Task<T?> ExecuteScalarAsync<T>(this NpgsqlDataSource dataSource, string sql, object? param = null)
    {
        await using var conn = await dataSource.OpenConnectionAsync();
        return await conn.ExecuteScalarAsync<T>(sql, param);
    }
}
