# TNTN Quiz (Laravel 12 + Realtime)

Backend + frontend SPA cho hệ thống quiz realtime theo mô hình **Host / Player**.

- Host tạo phòng, mở câu hỏi, xem kết quả realtime, kết thúc/giải tán phòng.
- Player vào phòng, trả lời câu hỏi, nhận cập nhật realtime qua WebSocket.
- Hỗ trợ câu hỏi có **text + nhiều ảnh**.

## 1. Tech Stack

- PHP 8.4, Laravel 12
- Laravel Reverb (WebSocket)
- Laravel Octane (tùy chọn)
- MySQL + Redis
- Filament v5 (admin)
- React 19 + Vite + Tailwind v4
- Pest 4 (test)

## 2. Tính năng chính

- Đăng nhập host bằng cookie token (`/api/quiz/host/login`)
- Tạo room, join room theo `room_code`
- Realtime room updates qua channel presence `quiz-room.{roomCode}`
- Countdown câu hỏi + đóng câu hỏi tự động theo thời gian
- Thống kê đáp án theo giới tính và tổng số
- Host có QR full-screen để player quét vào link join room
- Chặn player rời phòng đột ngột (back/reload có modal xác nhận)

## 3. Cài đặt nhanh (Local, không Docker)

### Yêu cầu

- PHP >= 8.2 (khuyến nghị 8.4)
- Composer
- Node.js + npm
- MySQL
- Redis

### Các bước

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
```

Chạy ứng dụng:

```bash
composer run dev
```

Lệnh này chạy đồng thời:
- Laravel server
- Queue listener
- Log tail (`pail`)
- Vite dev server

## 4. Chạy bằng Docker Compose (Sail style)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec quiz_app php artisan key:generate
docker compose exec quiz_app php artisan migrate
docker compose exec quiz_app php artisan db:seed
docker compose exec quiz_app php artisan storage:link
```

Các port chính (theo `.env`):
- App: `${APP_PORT}` (mặc định 80)
- Vite: `${VITE_PORT}` (mặc định 5173)
- Reverb: `${REVERB_SERVER_PORT}` (mặc định 8080)

## 5. Biến môi trường quan trọng

### Database

- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### Reverb / WebSocket

- `BROADCAST_CONNECTION=reverb`
- `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`
- `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
- `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`

### Host account seed

- `QUIZ_HOST_NAME`
- `QUIZ_HOST_EMAIL`
- `QUIZ_HOST_PASSWORD`

Ghi chú:
- Môi trường `local`: seeder host mặc định password `123456`.
- Môi trường `production`: bắt buộc set `QUIZ_HOST_PASSWORD`.

## 6. Tài khoản và dữ liệu mẫu

Seeder mặc định trong `DatabaseSeeder`:
- `HostAccountSeeder`
- `SampleQuizSeeder`

Đăng nhập host mẫu:
- Email: theo `QUIZ_HOST_EMAIL` (mặc định `host@quiz.local`)
- Password local: `123456`

## 7. Routes quan trọng

### Web routes

- `/` trang chọn vai trò
- `/host` host console
- `/host/{roomCode}` host room detail
- `/room/join` player join
- `/room/join/{roomCode}` player join trực tiếp theo mã phòng
- `/admin` Filament admin panel

### API routes (prefix `/api/quiz`)

Host:
- `POST /host/login`
- `GET /host/quizzes`
- `GET /host/rooms`
- `GET /host/rooms/{roomCode}`
- `POST /host/rooms`

Room / Play:
- `POST /rooms/{roomCode}/join`
- `GET /rooms/{roomCode}/players`
- `POST /rooms/{roomCode}/host/next`
- `POST /rooms/{roomCode}/host/finish`
- `POST /rooms/{roomCode}/host/dissolve`
- `POST /rooms/{roomCode}/answers`
- `GET /rooms/{roomCode}/state`
- `GET /rooms/{roomCode}/results`

## 8. Câu hỏi có ảnh

Model `QuizQuestion` hỗ trợ:
- `question_text` (nullable)
- `question_images` (array/json)

Ảnh được upload public disk (`storage/app/public`) và expose qua `storage:link`.

Nếu UI không hiển thị ảnh:
1. `php artisan storage:link`
2. kiểm tra `APP_URL`
3. build lại frontend (`npm run dev` hoặc `npm run build`)

## 9. Realtime troubleshooting

Nếu WebSocket không connect:
1. kiểm tra biến `REVERB_*` và `VITE_REVERB_*`
2. đảm bảo service Reverb đang chạy (`php artisan reverb:start` hoặc container `reverb`)
3. mở port Reverb tương ứng
4. kiểm tra mixed content (HTTP/HTTPS lệch scheme)

## 10. Build frontend

Dev:

```bash
npm run dev
```

Production:

```bash
npm run build
```

Ghi chú: cảnh báo `Some chunks are larger than 500 kB` là warning tối ưu hiệu năng, không phải lỗi build.

## 11. Test & Code Style

Run test:

```bash
php artisan test --compact
```

Format PHP:

```bash
vendor/bin/pint --format agent
```

## 12. Cấu trúc thư mục chính

- `app/Http/Controllers/Api` API cho host/player
- `app/Events` broadcast events
- `app/Jobs` job đóng câu hỏi
- `app/Filament` resources quản trị quiz
- `resources/js/quiz` React SPA (host/player)
- `database/migrations` schema
- `database/seeders` dữ liệu seed

## 13. License

MIT License

Copyright (c) 2026

## 14. Author

- LinkedIn: [Thắng Lợi Trần](https://www.linkedin.com/in/thắng-lợi-trần/)
