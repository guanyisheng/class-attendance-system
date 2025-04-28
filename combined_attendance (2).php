<?php
session_start();

// 数据库连接信息
$servername = "localhost";
$username = "kaoqin";
$password = "kaoqin";
$dbname = "kaoqin";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接是否成功
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 预设管理员账号
$adminUsername = "admin";
$adminPassword = "admin123";
$checkAdmin = $conn->query("SELECT id FROM users WHERE username = '$adminUsername'");
if ($checkAdmin->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?,?)");
    $stmt->bind_param("ss", $adminUsername, $adminPassword);
    $stmt->execute();
    $stmt->close();
}

// 用户注册处理
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $newUser = $_POST['newUsername'];
    $newPass = $_POST['newPassword'];
    $checkUser = $conn->query("SELECT id FROM users WHERE username = '$newUser'");
    if ($checkUser->num_rows > 0) {
        $register_error = "用户名已存在，请选择其他用户名。";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?,?)");
        $stmt->bind_param("ss", $newUser, $newPass);
        $stmt->execute();
        $stmt->close();
        $register_success = "注册成功，请登录。";
    }
}

// 用户登录处理
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE username =? AND password =?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['user_id'] = $result->fetch_assoc()['id'];
        header("Location: ". $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = "用户名或密码错误";
    }
}

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    // 未登录，显示登录和注册页面
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>考勤系统登录与注册</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 font-sans flex justify-center items-center h-screen">
    <div class="bg-white p-8 rounded shadow-md">
        <h2 class="text-lg font-bold mb-4">登录</h2>
        <?php if (isset($login_error)): ?>
            <p class="text-red-500 mb-2"><?php echo $login_error; ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="text" name="username" class="w-full border border-gray-300 p-2 mb-2" placeholder="用户名">
            <input type="password" name="password" class="w-full border border-gray-300 p-2 mb-2" placeholder="密码">
            <button type="submit" name="login" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">登录</button>
        </form>
        <hr class="my-4">
        <h2 class="text-lg font-bold mb-4">注册</h2>
        <?php if (isset($register_error)): ?>
            <p class="text-red-500 mb-2"><?php echo $register_error; ?></p>
        <?php endif; ?>
        <?php if (isset($register_success)): ?>
            <p class="text-green-500 mb-2"><?php echo $register_success; ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="text" name="newUsername" class="w-full border border-gray-300 p-2 mb-2" placeholder="新用户名">
            <input type="password" name="newPassword" class="w-full border border-gray-300 p-2 mb-2" placeholder="新密码">
            <button type="submit" name="register" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">注册</button>
        </form>
    </div>
</body>

</html>
<?php
    exit;
}

// 学生名单预设处理
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['savePreset'])) {
    $presetName = $_POST['presetName'];
    $studentList = $_POST['studentList'];
    $stmt = $conn->prepare("INSERT INTO presets (user_id, name, student_list) VALUES (?,?,?)");
    $stmt->bind_param("iss", $_SESSION['user_id'], $presetName, $studentList);
    $stmt->execute();
    $stmt->close();
}

// 获取用户的预设名单
$presets = [];
$stmt = $conn->prepare("SELECT id, name FROM presets WHERE user_id =?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $presets[] = $row;
}

// 选择预设名单处理
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selectPreset'])) {
    $presetId = $_POST['presetId'];
    $stmt = $conn->prepare("SELECT student_list FROM presets WHERE id =? AND user_id =?");
    $stmt->bind_param("ii", $presetId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $studentList = $result->fetch_assoc()['student_list'];
        $students = explode("\n", $studentList);
        $students = array_map('trim', $students);
        $students = array_filter($students);
        $attendances = array_fill(0, count($students), '正常');
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['importStudents'])) {
        $students = explode("\n", $_POST['studentList']);
        $students = array_map('trim', $students);
        $students = array_filter($students);

        foreach ($students as $student) {
            $stmt = $conn->prepare("INSERT INTO students (name) VALUES (?)");
            $stmt->bind_param("s", $student);
            $stmt->execute();
            $stmt->close();
        }
        $attendances = array_fill(0, count($students), '正常');
    } elseif (isset($_POST['confirmAttendance'])) {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $students = $_POST['students'];
        $attendances = $_POST['attendances'];

        // 获取当天的考勤次数
        $stmt = $conn->prepare("SELECT MAX(attendance_order) as max_order FROM attendances WHERE attendance_date =?");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $order = ($row['max_order']? $row['max_order'] + 1 : 1);

        foreach ($students as $index => $student) {
            $stmt = $conn->prepare("SELECT id FROM students WHERE name =?");
            $stmt->bind_param("s", $student);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $student_id = $row['id'];
                $status = $attendances[$index];

                $stmt = $conn->prepare("INSERT INTO attendances (student_id, attendance_date, attendance_time, attendance_order, status) VALUES (?,?,?,?,?)");
                $stmt->bind_param("issis", $student_id, $date, $time, $order, $status);
                $stmt->execute();
            }
            $stmt->close();
        }

        $abnormal = [];
        foreach ($students as $index => $student) {
            if ($attendances[$index]!== '正常') {
                $abnormal[] = [
                    'name' => $student,
                   'status' => $attendances[$index]
                ];
            }
        }
    } elseif (isset($_POST['queryDate'])) {
        $queryDate = $_POST['queryDate'];
        // 查询该日期所有不同的考勤时间点
        $sql = "SELECT DISTINCT attendance_time FROM attendances WHERE attendance_date =?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $queryDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $queryTimes = [];
        while ($row = $result->fetch_assoc()) {
            $queryTimes[] = $row['attendance_time'];
        }

        if (empty($queryTimes)) {
            $queryMessage = '该日期暂无考勤记录。';
        } elseif (isset($_POST['queryTime'])) {
            $queryTime = $_POST['queryTime'];
            $sql = "SELECT s.name, a.status
                    FROM students s
                    JOIN attendances a ON s.id = a.student_id
                    WHERE a.attendance_date =? AND a.attendance_time =?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $queryDate, $queryTime);
            $stmt->execute();
            $result = $stmt->get_result();

            $queryAttendanceData = [];
            while ($row = $result->fetch_assoc()) {
                $queryAttendanceData[] = $row;
            }
        }
    } elseif (isset($_POST['searchStudent'])) {
        $searchName = $_POST['searchName'];
        $sql = "SELECT s.name, a.attendance_date, a.attendance_time, a.status
                FROM students s
                JOIN attendances a ON s.id = a.student_id
                WHERE s.name LIKE ?";
        $stmt = $conn->prepare($sql);
        $searchPattern = "%$searchName%";
        $stmt->bind_param("s", $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();

        $searchAttendanceData = [];
        while ($row = $result->fetch_assoc()) {
            $searchAttendanceData[] = $row;
        }
        if (empty($searchAttendanceData)) {
            $searchMessage = '未找到该学生的考勤记录。';
        }
    } elseif (isset($_POST['exportAll'])) {
        $sql = "SELECT s.name, a.attendance_date, a.attendance_time, a.status
                FROM students s
                JOIN attendances a ON s.id = a.student_id";
        $result = $conn->query($sql);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="all_attendance.csv"');
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
        fputcsv($output, ['学生姓名', '考勤日期', '考勤时间', '考勤状态']);

        while ($data = $result->fetch_assoc()) {
            fputcsv($output, [
                $data['name'],
                $data['attendance_date'],
                $data['attendance_time'],
                $data['status']
            ]);
        }

        fclose($output);
        exit;
    } elseif (isset($_POST['exportAbnormal'])) {
        $sql = "SELECT s.name, a.attendance_date, a.attendance_time, a.status
                FROM students s
                JOIN attendances a ON s.id = a.student_id
                WHERE a.status != '正常'";
        $result = $conn->query($sql);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="abnormal_attendance.csv"');
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
        fputcsv($output, ['学生姓名', '考勤日期', '考勤时间', '考勤状态']);

        while ($data = $result->fetch_assoc()) {
            fputcsv($output, [
                $data['name'],
                $data['attendance_date'],
                $data['attendance_time'],
                $data['status']
            ]);
        }

        fclose($output);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>课堂考勤系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radioButtons = document.querySelectorAll('input[type="radio"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function () {
                    const parent = this.parentNode;
                    const siblings = parent.parentNode.querySelectorAll('.status-btn');
                    siblings.forEach(sibling => {
                        sibling.classList.remove('bg-opacity-75', 'shadow-lg');
                        sibling.classList.add('bg-opacity-20');
                    });
                    if (this.checked) {
                        const label = this.nextElementSibling;
                        label.classList.remove('bg-opacity-20');
                        label.classList.add('bg-opacity-75', 'shadow-lg');
                    }
                });
            });
        });
    </script>
</head>

<body class="bg-gray-100 font-sans">
    <!-- 头部 -->
    <header class="bg-blue-500 text-white text-center py-4">
        <h1 class="text-2xl font-bold">课堂考勤系统</h1>
    </header>

    <!-- 学生名单预设 -->
    <section class="p-4">
        <div class="bg-white p-4 rounded shadow-md">
            <h2 class="text-lg font-bold mb-2">预设学生名单</h2>
            <form method="post">
                <input type="text" name="presetName" class="w-full border border-gray-300 p-2 mb-2" placeholder="预设名称">
                <textarea name="studentList" class="w-full border border-gray-300 p-2 mb-2" rows="5"
                    placeholder="每行输入一个学生姓名"></textarea>
                <button type="submit" name="savePreset"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">保存预设</button>
            </form>
        </div>
    </section>

    <!-- 选择预设名单 -->
    <section class="p-4">
        <div class="bg-white p-4 rounded shadow-md">
            <h2 class="text-lg font-bold mb-2">选择预设名单</h2>
            <form method="post">
                <select name="presetId" class="w-full border border-gray-300 p-2 mb-2">
                    <?php foreach ($presets as $preset): ?>
                        <option value="<?php echo $preset['id']; ?>"><?php echo $preset['name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="selectPreset"
                    class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">选择</button>
            </form>
        </div>
    </section>

    <!-- 学生名单导入 -->
    <section class="p-4">
        <div class="bg-white p-4 rounded shadow-md">
            <h2 class="text-lg font-bold mb-2">导入学生名单</h2>
            <form method="post">
                <textarea name="studentList" class="w-full border border-gray-300 p-2 mb-2" rows="5"
                    placeholder="每行输入一个学生姓名"></textarea>
                <button type="submit" name="importStudents"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">导入学生</button>
            </form>
        </div>
    </section>

    <!-- 考勤表格 -->
    <?php if (isset($students)): ?>
        <section id="attendanceSection" class="p-4">
            <div class="bg-white p-4 rounded shadow-md">
                <h2 class="text-lg font-bold mb-2">考勤记录</h2>
                <form method="post">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="border border-gray-300 p-2">学生姓名</th>
                                <th class="border border-gray-300 p-2">考勤状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td style="min-width: 120px; max-width: 120px; word-break: keep-all; white-space: nowrap;">
                                        <?php echo $student; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusTypes = ['正常', '事假', '病假', '公假', '迟到', '缺勤'];
                                        $statusClasses = [
                                            'bg-green-500 hover:bg-green-600 focus:ring-green-300',
                                            'bg-yellow-400 hover:bg-yellow-500 focus:ring-yellow-300',
                                            'bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-300',
                                            'bg-orange-500 hover:bg-orange-600 focus:ring-orange-300',
                                            'bg-red-500 hover:bg-red-600 focus:ring-red-300',
                                            'bg-purple-500 hover:bg-purple-600 focus:ring-purple-300'
                                        ];
                                        foreach ($statusTypes as $idx => $status) {
                                            $selected = (isset($attendances[$index]) && $attendances[$index] === $status)? 'selected bg-opacity-75 shadow-lg' : 'bg-opacity-20';
                                            echo '<label class="inline-flex items-center mr-2">';
                                            echo '<input type="radio" name="attendances['.$index.']" value="'.$status.'" '.((!isset($attendances[$index]) && $status === '正常') || (isset($attendances[$index]) && $attendances[$index] === $status)? 'checked' : '').' class="hidden">';
                                            echo '<span class="status-btn '.$statusClasses[$idx].' '.$selected.' text-white px-3 py-1 rounded-md transition duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2">'.$status.'</span>';
                                            echo '</label>';
                                        }
                                        echo '<input type="hidden" name="students['.$index.']" value="'.$student.'">';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" name="confirmAttendance"
                        class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 mt-4">确认考勤</button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <!-- 异常考勤 -->
    <?php if (isset($abnormal) &&!empty($abnormal)): ?>
        <section id="abnormalSection" class="p-4">
            <div class="bg-white p-4 rounded shadow-md">
                <h2 class="text-lg font-bold mb-2">异常考勤记录</h2>
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 p-2">学生姓名</th>
                            <th class="border border-gray-300 p-2">考勤状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($abnormal as $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo $item['status']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- 日期查询 -->
    <section class="p-4">
        <div class="bg-white p-4 rounded shadow-md">
            <h2 class="text-lg font-bold mb-2">按日期查询考勤记录</h2>
            <form method="post">
                <input type="date" name="queryDate" class="border border-gray-300 p-2 mb-2">
                <button type="submit"
                    class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">查询</button>
            </form>
            <?php if (isset($queryMessage)): ?>
                <p><?php echo $queryMessage; ?></p>
            <?php elseif (isset($queryTimes)): ?>
                <p>该日期的考勤时间点有：</p>
                <?php foreach ($queryTimes as $time): ?>
                    <form method="post">
                        <input type="hidden" name="queryDate" value="<?php echo $queryDate; ?>">
                        <input type="hidden" name="queryTime" value="<?php echo $time; ?>">
                        <button type="submit"
                            class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 m-1"><?php echo $time; ?></button>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (isset($queryAttendanceData) &&!empty($queryAttendanceData)): ?>
            <div class="bg-white p-4 rounded shadow-md mt-4">
                <h2 class="text-lg font-bold mb-2">查询结果（<?php echo $_POST['queryTime']; ?>）</h2>
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 p-2">学生姓名</th>
                            <th class="border border-gray-300 p-2">考勤状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queryAttendanceData as $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo $item['status']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- 人员搜索 -->
    <section class="p-4">
        <div class="bg-white p-4 rounded shadow-md">
            <h2 class="text-lg font-bold mb-2">搜索人员考勤记录</h2>
            <form method="post">
                <input type="text" name="searchName" class="border border-gray-300 p-2 mb-2" placeholder="请输入学生姓名">
                <button type="submit" name="searchStudent"
                    class="bg-teal-500 text-white px-4 py-2 rounded hover:bg-teal-600">搜索</button>
            </form>
            <?php if (isset($searchMessage)): ?>
                <p><?php echo $searchMessage; ?></p>
            <?php elseif (isset($searchAttendanceData) &&!empty($searchAttendanceData)): ?>
                <div class="bg-white p-4 rounded shadow-md mt-4">
                    <h2 class="text-lg font-bold mb-2">搜索结果</h2>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="border border-gray-300 p-2">学生姓名</th>
                                <th class="border border-gray-300 p-2">考勤日期</th>
                                <th class="border border-gray-300 p-2">考勤时间</th>
                                <th class="border border-gray-300 p-2">考勤状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchAttendanceData as $item): ?>
                                <tr>
                                    <td><?php echo $item['name']; ?></td>
                                    <td><?php echo $item['attendance_date']; ?></td>
                                    <td><?php echo $item['attendance_time']; ?></td>
                                    <td><?php echo $item['status']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 导出按钮 -->
    <section class="p-4">
        <div class="bg-white p-4 rounded shadow-md">
            <h2 class="text-lg font-bold mb-2">导出考勤记录</h2>
            <form method="post">
                <button type="submit" name="exportAll"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 mr-2">导出全部</button>
                <button type="submit" name="exportAbnormal"
                    class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">导出异常</button>
            </form>
        </div>
    </section>

    <!-- 页脚 -->
    <footer class="bg-gray-800 text-white text-center py-2">
        <p>&copy; <?php echo date('Y'); ?> 课堂考勤系统</p>
    </footer>
</body>

</html>    