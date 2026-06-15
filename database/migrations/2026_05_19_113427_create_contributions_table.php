public function up(): void
{
Schema::create('communities', function (Blueprint $table) {
$table->id();
$table->string('name');
$table->text('description')->nullable();
$table->foreignId('created_by')->constrained('users')->onDelete('cascade');
$table->timestamps();
});
}

public function down(): void
{
Schema::dropIfExists('communities');
}