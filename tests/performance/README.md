# Performance Benchmarks

## Targets

- **Validation**: < 10ms per share
- **Load 100 shares**: < 100ms
- **Save 100 shares**: < 100ms
- **Generate config**: < 50ms for 100 shares
- **Memory usage**: < 5MB for 100 shares

## Running Benchmarks

```bash
vendor/bin/phpunit tests/performance/PerformanceBenchmark.php --testdox
```

## Results Interpretation

- ✅ All tests passing = Performance targets met
- ❌ Test failures = Performance regression detected

## Adding New Benchmarks

1. Add test method to `PerformanceBenchmark.php`
2. Use `microtime(true)` for timing
3. Assert against target threshold
4. Document target in this README
