# 瑞格米客户端接入文档

> 本文档面向**客户端开发人员**，涵盖所有平台（Windows、Linux、macOS、Android、iOS）的接入指南。

---

## 1. 基础信息

### 1.1 接口根地址
```
https://ragemi.com/api/
```

### 1.2 认证方式

| 方式 | 说明 | 适用场景 |
|------|------|----------|
| **API Key** | 在请求头携带 `X-API-Key` | Bot 自动化操作 |
| **Bearer Token** | 在请求头携带 `Authorization: Bearer {token}` | 用户登录后的操作 |
| **OAuth 2.0** | 授权码流程 | 第三方网站/应用接入 |

### 1.3 通用请求头
```
Content-Type: application/json
Accept: application/json
User-Agent: RagemiClient/1.0 (Platform; OS Version)
```

### 1.4 通用响应格式
```json
{
  "code": 200,
  "msg": "操作成功",
  "data": { ... }
}
```

| code | 含义 |
|------|------|
| 200 | 成功 |
| 400 | 参数错误 |
| 401 | 未授权 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 429 | 请求过于频繁 |
| 500 | 服务器错误 |

---

## 2. 认证接口

### 2.1 用户登录（获取 Token）

```
POST /api/login
```

**请求参数：**
```json
{
  "username": "myuser",      // 用户名或邮箱
  "password": "123456",
  "remember": true            // 可选，延长有效期至7天
}
```

**响应：**
```json
{
  "code": 200,
  "data": {
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 7200,
    "refresh_token": "...",
    "user": {
      "id": 123,
      "username": "myuser",
      "display_name": "昵称",
      "avatar": "https://ragemi.com/uploads/avatars/avatar.jpg"
    }
  }
}
```

### 2.2 刷新 Token

```
POST /api/refresh
```
```json
{ "refresh_token": "..." }
```

### 2.3 退出登录

```
POST /api/logout
```
请求头：`Authorization: Bearer {access_token}`

---

## 3. 帖子接口

### 3.1 获取时间线（首页）

```
GET /api/timeline?page=1&limit=20
```

### 3.2 获取帖子详情

```
GET /api/post?id=123
```

### 3.3 发布帖子

```
POST /api/post_create
```
**请求体（multipart/form-data）：**
```
content: 帖子内容
images[]: 图片文件（最多9张，单张≤2MB）
```

### 3.4 删除帖子（撤回）

```
DELETE /api/post_delete
```
```json
{ "post_id": 123 }
```
（仅限发布后24小时内）

---

## 4. 互动接口

### 4.1 点赞/取消点赞

```
POST /api/like
```
```json
{ "post_id": 123 }
```
响应：`{ "liked": true }`

### 4.2 发布评论

```
POST /api/comment_create
```
```json
{ "post_id": 123, "content": "评论内容" }
```

### 4.3 获取评论列表

```
GET /api/comments?post_id=123&page=1
```

---

## 5. 用户接口

### 5.1 获取当前用户信息

```
GET /api/user_me
```

### 5.2 获取用户主页信息

```
GET /api/user?subdomain=username
```

### 5.3 关注/取消关注

```
POST /api/follow
```
```json
{ "user_id": 456 }
```
响应：`{ "following": true }`

---

## 6. 私信接口

### 6.1 发送私信

```
POST /api/message
```
```json
{ "receiver_id": 456, "content": "消息内容" }
```

### 6.2 获取私信列表

```
GET /api/messages?to=456&page=1
```

### 6.3 获取未读私信数量

```
GET /api/messages/unread
```

---

## 7. 通知接口

### 7.1 获取通知列表

```
GET /api/notifications
```

### 7.2 标记全部已读

```
PUT /api/notifications_read
```

---

## 8. OAuth 2.0（第三方登录）

### 8.1 授权流程

1. 引导用户访问：
```
https://ragemi.com/oss?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_CALLBACK&response_type=code&scope=basic&state=RANDOM
```

2. 用户授权后，回调带上 `code`：
```
https://your-app.com/callback?code=AUTH_CODE&state=...
```

3. 服务端换取 Token：
```
POST https://ragemi.com/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=AUTH_CODE&client_id=YOUR_CLIENT_ID&client_secret=YOUR_CLIENT_SECRET&redirect_uri=YOUR_CALLBACK
```

### 8.2 获取用户信息

```
GET https://ragemi.com/api/user_me
Authorization: Bearer {access_token}
```

---

## 9. 搜索接口

```
GET /api/search?q=关键词&type=all&page=1
```

| type | 说明 |
|------|------|
| `all` | 全部 |
| `posts` | 仅帖子 |
| `users` | 仅用户 |

---

## 10. 平台接入指南

### 10.1 Windows（C# / .NET）

```csharp
using System.Net.Http;
using System.Text.Json;

var client = new HttpClient();
client.DefaultRequestHeaders.Add("Authorization", "Bearer {token}");

var response = await client.GetAsync("https://ragemi.com/api/timeline");
var json = await response.Content.ReadAsStringAsync();
var data = JsonSerializer.Deserialize<dynamic>(json);
```

### 10.2 Linux / macOS（curl / Shell）

```bash
curl -X GET https://ragemi.com/api/timeline \
  -H "Authorization: Bearer {token}"
```

### 10.3 Android（Kotlin）

```kotlin
val client = OkHttpClient()
val request = Request.Builder()
    .url("https://ragemi.com/api/timeline")
    .addHeader("Authorization", "Bearer $token")
    .build()
client.newCall(request).execute().use { response ->
    val json = response.body?.string()
}
```

### 10.4 iOS（Swift）

```swift
var request = URLRequest(url: URL(string: "https://ragemi.com/api/timeline")!)
request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
let task = URLSession.shared.dataTask(with: request) { data, _, _ in
    // 处理 data
}
task.resume()
```