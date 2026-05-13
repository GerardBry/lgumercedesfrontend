#!/usr/bin/env python3
import os

file_path = r'c:\xampp\htdocs\LGU draft Design Front End\administrative\outgoing.php'

with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Find and fix the problematic line
fixed_lines = []
i = 0
while i < len(lines):
    line = lines[i]
    # Check for the problematic escape sequence
    if r"alert('Travel Request Details:\n\n' + dataStr);\n        }" in line:
        # Replace with clean version
        fixed_lines.append("            alert('Travel Request Details: ' + dataStr);\n")
        fixed_lines.append("        }\n")
        i += 1
    else:
        fixed_lines.append(line)
        i += 1

# Add the missing file management functions before closing script tag
final_lines = []
for i, line in enumerate(fixed_lines):
    final_lines.append(line)
    if '</script>' in line and i > 100:  # Find the last closing script tag
        # Insert functions before this tag
        final_lines.insert(-1, """
        window.viewUploadedFileOutgoing = function(filePath, fileExt) {
            filePath = decodeURIComponent(filePath);
            const isPDF = fileExt === 'pdf';
            if (isPDF) {
                window.open('view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                const url = 'view-document-file.php?path=' + encodeURIComponent(filePath);
                document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + filePath.split('/').pop();
                document.getElementById('fileViewerImage').src = url;
                document.getElementById('fileViewerModal').classList.add('active');
            } else {
                window.open('view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
            }
        };

        window.downloadUploadedFileOutgoing = function(filePath, fileName) {
            filePath = decodeURIComponent(filePath);
            const url = 'get-document-file.php?path=' + encodeURIComponent(filePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };

        window.deleteUploadedFileOutgoing = function(uploadId, assignmentId) {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }
            fetch('delete-document-upload.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({upload_id: uploadId, assignment_id: assignmentId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File deleted successfully!');
                    window.loadUploadedFiles(assignmentId);
                } else {
                    alert('Error deleting file: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => alert('Error: ' + error.message));
        };
""")
        break

with open(file_path, 'w', encoding='utf-8') as f:
    f.writelines(final_lines)

print('File fixed successfully')
