import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../../core/components/components.dart';
import '../../../core/constants/colors.dart';

class MenuButton extends StatelessWidget {
  final String label;
  final String? iconPath;
  final IconData? iconData;
  final VoidCallback onPressed;
  final Color backgroundColor;
  final Color foregroundColor;

  const MenuButton({
    super.key,
    required this.label,
    this.iconPath,
    this.iconData,
    required this.onPressed,
    this.backgroundColor = AppColors.white,
    this.foregroundColor = AppColors.primary,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(24.0),
        boxShadow: [
          BoxShadow(
            color: AppColors.black.withOpacity(0.08),
            blurRadius: 20.0,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(24.0),
        child: InkWell(
          onTap: onPressed,
          borderRadius: BorderRadius.circular(24.0),
          splashColor: foregroundColor.withOpacity(0.1),
          highlightColor: foregroundColor.withOpacity(0.05),
          child: Padding(
            padding:
                const EdgeInsets.symmetric(vertical: 12.0, horizontal: 8.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(12.0),
                  decoration: BoxDecoration(
                    color: foregroundColor.withOpacity(0.15),
                    shape: BoxShape.circle,
                  ),
                  child: iconPath != null
                      ? SvgPicture.asset(
                          iconPath!,
                          width: 28.0,
                          height: 28.0,
                        )
                      : Icon(
                          iconData ?? Icons.widgets,
                          size: 28.0,
                          color: foregroundColor,
                        ),
                ),
                const SpaceHeight(8.0),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13.0,
                    fontWeight: FontWeight.w700,
                    color: AppColors.black,
                    letterSpacing: -0.2,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
