import { Model, DataTypes } from 'sequelize';

export default class LessonDependencies extends Model {
  static init(sequelize) {
    super.init(
      {
        lessonId: {
          type: DataTypes.INTEGER,
          references: {
            model: 'lessons',
            key: 'id',
          },
        },
        nextLessonId: {
          type: DataTypes.INTEGER,
          references: {
            model: 'lessons',
            key: 'id',
          },
        },
        previousLessonId: {
          type: DataTypes.INTEGER,
          references: {
            model: 'lessons',
            key: 'id',
          },
        },
      },
      {
        sequelize,
        modelName: "lessonDependencies",
      }
    );
  }

  static associate(models) {
    this.belongsTo(models.lesson, {
      foreignKey: 'lessonId',
      as: 'lesson',
    });
    this.belongsTo(models.lesson, {
      foreignKey: 'nextLessonId',
      as: 'nextLesson',
    });
    this.belongsTo(models.lesson, {
      foreignKey: 'previousLessonId',
      as: 'previousLesson',
    });
  }
}
