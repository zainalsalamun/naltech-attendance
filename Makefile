.PHONY: help backend-install mobile-install web-install backend-serve mobile-run web-dev backend-test mobile-test mobile-analyze

help:
	@echo "Smart Attendance: backend-install mobile-install web-install backend-serve mobile-run web-dev backend-test mobile-test mobile-analyze"

backend-install:
	composer --working-dir=apps/backend install

mobile-install:
	cd apps/mobile && flutter pub get

web-install:
	npm --prefix apps/backend ci

backend-serve:
	cd apps/backend && php artisan serve --host=0.0.0.0 --port=8000

mobile-run:
	cd apps/mobile && flutter run

web-dev:
	npm --prefix apps/backend run dev

backend-test:
	cd apps/backend && php vendor/bin/phpunit tests/Feature/AttendanceIntegrityTest.php --no-progress

mobile-test:
	cd apps/mobile && flutter test test/attendance_qr_proof_test.dart --no-pub

mobile-analyze:
	cd apps/mobile && dart analyze lib/data
